<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money;
use App\Domain\ValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;

final class CustomerPortalService
{
    /** @return list<array{id:int,username:string,email:string}> */
    public function invitationCandidates(): array
    {
        $rows = db_connect()->query("SELECT u.id, u.username, COALESCE(ai.secret, '') email
            FROM users u
            JOIN auth_groups_users agu ON agu.user_id = u.id AND agu.group = 'cliente'
            LEFT JOIN auth_identities ai ON ai.user_id = u.id AND ai.type = 'email_password'
            LEFT JOIN cliente_usuarios cu ON cu.usuario_id = u.id
            WHERE u.active = 1 AND u.deleted_at IS NULL AND cu.usuario_id IS NULL
            ORDER BY u.username, u.id")->getResultArray();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'email' => (string) $row['email'],
        ], $rows);
    }

    public function createInvitation(int $actorId, int $clientId, int $userId): string
    {
        Access::require($actorId, 'usuarios.editar');
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $client = $db->query('SELECT id FROM clientes WHERE id = ? FOR UPDATE', [$clientId])->getRowArray();
            if (!$client) {
                throw PageNotFoundException::forPageNotFound();
            }
            $candidate = $db->query("SELECT u.id FROM users u
                JOIN auth_groups_users agu ON agu.user_id = u.id AND agu.group = 'cliente'
                WHERE u.id = ? AND u.active = 1 AND u.deleted_at IS NULL
                  AND NOT EXISTS (SELECT 1 FROM cliente_usuarios cu WHERE cu.usuario_id = u.id)
                FOR UPDATE", [$userId])->getRowArray();
            if (!$candidate) {
                throw new ValidationException(['usuario_id' => 'Selecciona un usuario cliente activo que todavía no esté vinculado.']);
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $db->table('cliente_invitaciones')
                ->where('usuario_id', $userId)->where('usada_en', null)->where('revocada_en', null)
                ->update(['revocada_en' => $now]);
            $token = bin2hex(random_bytes(32));
            $db->table('cliente_invitaciones')->insert([
                'cliente_id' => $clientId,
                'usuario_id' => $userId,
                'token_hash' => hash('sha256', $token),
                'expira_en' => gmdate('Y-m-d H:i:s.u', time() + 7 * 86400),
                'creada_por' => $actorId,
                'creada_en' => $now,
            ]);
            Audit::record($actorId, 'portal.invitacion.crear', 'cliente:' . $clientId, ['usuario_id' => $userId]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo crear la invitación.');
            }

            return $token;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function invitation(int $actorId, string $token): array
    {
        $hash = $this->tokenHash($token);
        $row = db_connect()->query("SELECT ci.id, ci.cliente_id, ci.expira_en, c.nombre cliente,
                u.username
            FROM cliente_invitaciones ci
            JOIN clientes c ON c.id = ci.cliente_id
            JOIN users u ON u.id = ci.usuario_id
            WHERE ci.token_hash = ? AND ci.usuario_id = ? AND ci.usada_en IS NULL
              AND ci.revocada_en IS NULL AND ci.expira_en > UTC_TIMESTAMP(6)", [$hash, $actorId])->getRowArray();
        if (!$row) {
            throw PageNotFoundException::forPageNotFound();
        }
        $row['expira_en_local'] = $this->localDateTime((string) $row['expira_en']);

        return $row;
    }

    /** @return array{client_id:int} */
    public function activate(int $actorId, string $token, string $idempotencyKey): array
    {
        $hash = $this->tokenHash($token);
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'portal.invitacion.activar', $idempotencyKey, ['token_hash' => $hash]);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            $invitation = $db->query("SELECT id, cliente_id FROM cliente_invitaciones
                WHERE token_hash = ? AND usuario_id = ? AND usada_en IS NULL AND revocada_en IS NULL
                  AND expira_en > UTC_TIMESTAMP(6) FOR UPDATE", [$hash, $actorId])->getRowArray();
            if (!$invitation) {
                throw new ValidationException(['invitacion' => 'La invitación no existe, venció o ya fue utilizada.']);
            }
            if ($db->query('SELECT usuario_id FROM cliente_usuarios WHERE usuario_id = ? FOR UPDATE', [$actorId])->getRowArray()) {
                throw new ValidationException(['invitacion' => 'Tu cuenta ya está vinculada a un cliente.']);
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $db->table('cliente_usuarios')->insert([
                'usuario_id' => $actorId,
                'cliente_id' => (int) $invitation['cliente_id'],
                'invitacion_id' => (int) $invitation['id'],
                'operacion_vinculacion_id' => (int) $operation['id'],
                'vinculado_en' => $now,
            ]);
            $db->table('cliente_invitaciones')->where('id', (int) $invitation['id'])->update([
                'usada_en' => $now,
                'operacion_uso_id' => (int) $operation['id'],
            ]);
            $result = ['client_id' => (int) $invitation['cliente_id']];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'portal.invitacion.activar', 'cliente:' . (int) $invitation['cliente_id']);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo activar el acceso al portal.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array{client:array<string,mixed>,rows:list<array<string,mixed>>,totals:array<string,int>} */
    public function statements(int $actorId): array
    {
        $client = $this->linkedClient($actorId);
        $rows = db_connect()->query("SELECT r.id, r.importe_reportado, r.fecha_reportada, r.referencia_reportada,
                r.metodo, r.reportado_en, cb.institucion, cb.alias,
                COALESCE(rr.resultado, 'PENDIENTE') estado_codigo
            FROM reportes_pago r
            JOIN cuentas_bancarias cb ON cb.id = r.cuenta_bancaria_id
            LEFT JOIN reporte_revisiones rr ON rr.reporte_id = r.id
             AND rr.revision = (SELECT MAX(rr2.revision) FROM reporte_revisiones rr2 WHERE rr2.reporte_id = r.id)
            WHERE r.cliente_id = ? ORDER BY r.reportado_en DESC, r.id DESC", [(int) $client['id']])->getResultArray();
        $totals = ['pending' => 0, 'reviewed' => 0, 'rejected' => 0];
        foreach ($rows as &$row) {
            $row = $this->presentReport($row);
            if ($row['estado_codigo'] === 'PENDIENTE' || $row['estado_codigo'] === 'REABIERTO') {
                $totals['pending']++;
            } elseif ($row['estado_codigo'] === 'RECHAZADO') {
                $totals['rejected']++;
            } else {
                $totals['reviewed']++;
            }
        }
        unset($row);

        return ['client' => $client, 'rows' => $rows, 'totals' => $totals];
    }

    /** @return array<string, mixed> */
    public function createStatement(int $actorId, array $input, ?UploadedFile $file, string $idempotencyKey): array
    {
        $client = $this->linkedClient($actorId);

        return (new BankReconciliationService())->createForPortal($actorId, (int) $client['id'], $input, $file, $idempotencyKey);
    }

    /** @return array<string, mixed> */
    public function statement(int $actorId, int $reportId): array
    {
        $client = $this->linkedClient($actorId);
        $db = db_connect();
        $row = $db->query("SELECT r.id, r.importe_reportado, r.fecha_reportada, r.referencia_reportada,
                r.metodo, r.observacion, r.reportado_en, cb.institucion, cb.alias, cb.numero_cuenta,
                COALESCE(rr.resultado, 'PENDIENTE') estado_codigo
            FROM reportes_pago r
            JOIN cuentas_bancarias cb ON cb.id = r.cuenta_bancaria_id
            LEFT JOIN reporte_revisiones rr ON rr.reporte_id = r.id
             AND rr.revision = (SELECT MAX(rr2.revision) FROM reporte_revisiones rr2 WHERE rr2.reporte_id = r.id)
            WHERE r.id = ? AND r.cliente_id = ?", [$reportId, (int) $client['id']])->getRowArray();
        if (!$row) {
            throw PageNotFoundException::forPageNotFound();
        }
        $row = $this->presentReport($row);
        $row['files'] = $db->query("SELECT ra.id, ra.nombre_original, ra.tipo_mime, ra.tamano_bytes
            FROM reporte_archivos ra JOIN reportes_pago r ON r.id = ra.reporte_id
            WHERE ra.reporte_id = ? AND r.cliente_id = ? ORDER BY ra.id", [$reportId, (int) $client['id']])->getResultArray();
        $row['reviews'] = $db->query("SELECT rr.revision, rr.resultado, rr.notas, op.registrada_en
            FROM reporte_revisiones rr
            JOIN operaciones op ON op.id = rr.operacion_id
            JOIN reportes_pago r ON r.id = rr.reporte_id
            WHERE rr.reporte_id = ? AND r.cliente_id = ? ORDER BY rr.revision DESC", [$reportId, (int) $client['id']])->getResultArray();
        foreach ($row['reviews'] as &$review) {
            $review['resultado_label'] = $this->statusLabel((string) $review['resultado']);
            $review['registrada_en_local'] = $this->localDateTime((string) $review['registrada_en']);
        }
        unset($review);
        $row['client'] = $client;

        return $row;
    }

    /** @return array<string, mixed> */
    public function file(int $actorId, int $fileId): array
    {
        $client = $this->linkedClient($actorId);
        $file = db_connect()->query("SELECT ra.* FROM reporte_archivos ra
            JOIN reportes_pago r ON r.id = ra.reporte_id
            WHERE ra.id = ? AND r.cliente_id = ?", [$fileId, (int) $client['id']])->getRowArray();
        if (!$file) {
            throw PageNotFoundException::forPageNotFound();
        }
        $root = realpath(WRITEPATH . 'uploads');
        $path = realpath(WRITEPATH . 'uploads/' . (string) $file['ruta_relativa']);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $file['absolute_path'] = $path;
        Audit::record($actorId, 'portal.reportes_pago.archivo.descargar', 'reporte_pago:' . (int) $file['reporte_id'], ['archivo_id' => $fileId]);

        return $file;
    }

    /** @return array<string, mixed> */
    public function linkedClient(int $actorId): array
    {
        $client = db_connect()->query("SELECT c.id, c.nombre, c.activo
            FROM cliente_usuarios cu JOIN clientes c ON c.id = cu.cliente_id
            WHERE cu.usuario_id = ?", [$actorId])->getRowArray();
        if (!$client) {
            throw new \RuntimeException('Tu cuenta todavía no está vinculada mediante una invitación.', 403);
        }

        return $client;
    }

    public function hasLink(int $actorId): bool
    {
        return db_connect()->table('cliente_usuarios')->where('usuario_id', $actorId)->countAllResults() === 1;
    }

    private function tokenHash(string $token): string
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }

        return hash('sha256', $token);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function presentReport(array $row): array
    {
        $row['monto'] = Money::decimal(Money::cents((string) $row['importe_reportado']));
        $row['estado'] = $this->statusLabel((string) $row['estado_codigo']);
        $row['fecha_local'] = (new \DateTimeImmutable((string) $row['fecha_reportada']))->format('d/m/Y');
        $row['reportado_en_local'] = $this->localDateTime((string) $row['reportado_en']);

        return $row;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'APROBADO' => 'Aprobado',
            'VINCULADO' => 'Verificado',
            'RECHAZADO' => 'Rechazado',
            'REABIERTO' => 'En nueva revisión',
            default => 'Pendiente',
        };
    }

    private function localDateTime(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
    }
}
