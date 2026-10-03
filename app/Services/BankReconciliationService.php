<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money;
use App\Domain\ValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;

final class BankReconciliationService
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    /** @return array<string, mixed> */
    public function inbox(array $input = []): array
    {
        $status = is_string($input['estado'] ?? null) ? strtoupper($input['estado']) : '';
        if (!in_array($status, ['', 'PENDIENTE', 'APROBADO', 'VINCULADO', 'RECHAZADO', 'REABIERTO'], true)) {
            $status = '';
        }
        $queryText = mb_substr(trim(is_string($input['q'] ?? null) ? $input['q'] : ''), 0, 100);
        $db = db_connect();
        $query = $db->table('reportes_pago r')
            ->join('clientes c', 'c.id = r.cliente_id', 'left')
            ->join('cuentas_bancarias cb', 'cb.id = r.cuenta_bancaria_id')
            ->join('reporte_revisiones rr', 'rr.reporte_id = r.id AND rr.revision = (SELECT MAX(rr2.revision) FROM reporte_revisiones rr2 WHERE rr2.reporte_id = r.id)', 'left', false);
        if ($queryText !== '') {
            $query->groupStart()->like('c.nombre', $queryText)->orLike('r.referencia_reportada', $queryText)->groupEnd();
        }
        if ($status === 'PENDIENTE') {
            $query->where('rr.id IS NULL', null, false);
        } elseif ($status !== '') {
            $query->where('rr.resultado', $status);
        }
        $rows = $query->select("r.id, r.importe_reportado monto, r.fecha_reportada fecha,
                r.referencia_reportada referencia, r.metodo, r.reportado_en,
                COALESCE(c.nombre, 'Sin identificar') cliente, cb.institucion banco, cb.alias cuenta,
                COALESCE(rr.resultado, 'PENDIENTE') estado_codigo", false)
            ->orderBy('r.reportado_en', 'DESC')->orderBy('r.id', 'DESC')->get()->getResultArray();
        $totals = ['Pendiente' => 0, 'Aprobado' => 0, 'Vinculado' => 0, 'Rechazado' => 0, 'Reabierto' => 0];
        foreach ($rows as &$row) {
            $row['estado'] = $this->statusLabel((string) $row['estado_codigo']);
            $totals[$row['estado']]++;
        }
        unset($row);

        return ['rows' => $rows, 'totals' => $totals, 'filters' => ['q' => $queryText, 'estado' => $status]];
    }

    /** @return array<string, mixed> */
    public function create(int $actorId, array $input, ?UploadedFile $file, string $idempotencyKey): array
    {
        Access::require($actorId, 'pagos.crear');

        return $this->persistReport($actorId, $input, $file, $idempotencyKey, false);
    }

    /** @return array<string, mixed> */
    public function createForPortal(int $actorId, int $clientId, array $input, ?UploadedFile $file, string $idempotencyKey): array
    {
        $linked = db_connect()->query("SELECT cu.cliente_id FROM cliente_usuarios cu
            JOIN users u ON u.id = cu.usuario_id AND u.active = 1 AND u.deleted_at IS NULL
            JOIN auth_groups_users agu ON agu.user_id = u.id AND agu.group = 'cliente'
            WHERE cu.usuario_id = ? AND cu.cliente_id = ?", [$actorId, $clientId])->getRowArray();
        if (!$linked) {
            throw new \RuntimeException('No tienes acceso a este cliente.', 403);
        }
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['attachment' => 'Adjunta el comprobante en PDF, PNG o JPEG.']);
        }
        $input['cliente_id'] = $clientId;

        return $this->persistReport($actorId, $input, $file, $idempotencyKey, true);
    }

    /** @return array<string, mixed> */
    private function persistReport(int $actorId, array $input, ?UploadedFile $file, string $idempotencyKey, bool $allowInactiveClient): array
    {
        $data = $this->validateReport($input);
        $upload = $this->validateOptionalUpload($file);
        $payload = $data + ['attachment_hash' => $upload['hash'] ?? null];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        $absolutePath = null;

        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'reportes_pago.registrar', $idempotencyKey, $payload);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            $account = $db->query('SELECT id, activa FROM cuentas_bancarias WHERE id = ? FOR UPDATE', [$data['bank_account_id']])->getRowArray();
            if (!$account || (int) $account['activa'] !== 1) {
                throw new ValidationException(['cuenta_bancaria_id' => 'Selecciona una cuenta bancaria activa.']);
            }
            if ($data['client_id'] !== null) {
                $allowInactiveClient
                    ? $this->requireClient($db, $data['client_id'])
                    : $this->requireActiveClient($db, $data['client_id']);
            }
            $db->table('reportes_pago')->insert([
                'cliente_id' => $data['client_id'],
                'cuenta_bancaria_id' => $data['bank_account_id'],
                'metodo' => $data['method'],
                'importe_reportado' => $data['amount'],
                'fecha_reportada' => $data['reported_on'],
                'referencia_reportada' => $data['reference'],
                'observacion' => $data['notes'] === '' ? null : $data['notes'],
                'operacion_id' => $operation['id'],
                'reportado_en' => gmdate('Y-m-d H:i:s.u'),
            ]);
            $reportId = (int) $db->insertID();
            if ($upload !== null && $file !== null) {
                $directory = WRITEPATH . 'uploads/reportes-pago/' . $reportId;
                if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                    throw new \RuntimeException('No se pudo preparar el almacenamiento privado.');
                }
                $storedName = bin2hex(random_bytes(20)) . '.' . $upload['extension'];
                $absolutePath = $directory . '/' . $storedName;
                $relativePath = 'reportes-pago/' . $reportId . '/' . $storedName;
                $file->move($directory, $storedName, false);
                $db->table('reporte_archivos')->insert([
                    'reporte_id' => $reportId,
                    'nombre_original' => $upload['name'],
                    'ruta_relativa' => $relativePath,
                    'tipo_mime' => $upload['mime'],
                    'extension' => $upload['extension'],
                    'tamano_bytes' => $upload['size'],
                    'hash_sha256' => $upload['hash'],
                    'cargado_por' => $actorId,
                    'cargado_en' => gmdate('Y-m-d H:i:s.u'),
                ]);
            }
            $result = ['report_id' => $reportId];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'reportes_pago.registrar', 'reporte_pago:' . $reportId, [
                'importe' => $data['amount'], 'cliente_id' => $data['client_id'], 'con_archivo' => $upload !== null,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo registrar el comprobante.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            if ($absolutePath !== null && is_file($absolutePath)) {
                unlink($absolutePath);
            }
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function find(int $reportId): array
    {
        $db = db_connect();
        $row = $db->query("SELECT r.*, c.nombre cliente, cb.institucion banco, cb.alias cuenta,
            COALESCE(rr.resultado, 'PENDIENTE') estado_codigo, rr.pago_id
            FROM reportes_pago r
            LEFT JOIN clientes c ON c.id = r.cliente_id
            JOIN cuentas_bancarias cb ON cb.id = r.cuenta_bancaria_id
            LEFT JOIN reporte_revisiones rr ON rr.reporte_id = r.id
             AND rr.revision = (SELECT MAX(rr2.revision) FROM reporte_revisiones rr2 WHERE rr2.reporte_id = r.id)
            WHERE r.id = ?", [$reportId])->getRowArray();
        if (!$row) {
            throw PageNotFoundException::forPageNotFound();
        }
        $row['monto'] = (string) $row['importe_reportado'];
        $row['fecha'] = (string) $row['fecha_reportada'];
        $row['referencia'] = (string) $row['referencia_reportada'];
        $row['estado'] = $this->statusLabel((string) $row['estado_codigo']);
        $row['files'] = $db->table('reporte_archivos')->where('reporte_id', $reportId)->orderBy('id')->get()->getResultArray();
        $row['reviews'] = $db->query("SELECT rr.revision, rr.resultado, rr.pago_id, rr.notas, op.registrada_en,
                u.username revisor
            FROM reporte_revisiones rr
            JOIN operaciones op ON op.id = rr.operacion_id
            JOIN users u ON u.id = op.usuario_id
            WHERE rr.reporte_id = ? ORDER BY rr.revision DESC", [$reportId])->getResultArray();
        foreach ($row['reviews'] as &$review) {
            $review['resultado_label'] = $this->statusLabel((string) $review['resultado']);
        }
        unset($review);
        $row['matches'] = $this->matchingPayments($row);

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function unidentified(): array
    {
        return db_connect()->query("SELECT r.id, r.importe_reportado monto, r.fecha_reportada fecha,
                r.referencia_reportada referencia, cb.institucion origen
            FROM reportes_pago r
            JOIN cuentas_bancarias cb ON cb.id = r.cuenta_bancaria_id
            LEFT JOIN reporte_revisiones rr ON rr.reporte_id = r.id
             AND rr.revision = (SELECT MAX(rr2.revision) FROM reporte_revisiones rr2 WHERE rr2.reporte_id = r.id)
            WHERE r.cliente_id IS NULL AND (rr.id IS NULL OR rr.resultado = 'REABIERTO')
            ORDER BY r.fecha_reportada, r.id")->getResultArray();
    }

    /** @return array<string, mixed> */
    public function identify(int $actorId, int $reportId, int $clientId, string $idempotencyKey): array
    {
        Access::require($actorId, 'pagos.crear');
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'reportes_pago.identificar', $idempotencyKey, [
                'report_id' => $reportId, 'client_id' => $clientId,
            ]);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            $report = $db->query('SELECT id, cliente_id FROM reportes_pago WHERE id = ? FOR UPDATE', [$reportId])->getRowArray();
            if (!$report) {
                throw PageNotFoundException::forPageNotFound();
            }
            if ($report['cliente_id'] !== null) {
                throw new ValidationException(['cliente_id' => 'El comprobante ya tiene un cliente identificado.']);
            }
            $this->requireActiveClient($db, $clientId);
            $db->table('reportes_pago')->where('id', $reportId)->update(['cliente_id' => $clientId]);
            $result = ['report_id' => $reportId, 'client_id' => $clientId];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'reportes_pago.identificar', 'reporte_pago:' . $reportId, ['cliente_id' => $clientId]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo identificar el comprobante.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function review(int $actorId, int $reportId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'pagos.bancarios.confirmar');
        $decision = strtoupper(trim(is_string($input['decision'] ?? null) ? $input['decision'] : ''));
        if (!in_array($decision, ['APROBADO', 'VINCULADO', 'RECHAZADO', 'REABIERTO'], true)) {
            throw new ValidationException(['decision' => 'Selecciona una decisión válida.']);
        }
        $notes = mb_substr(trim((string) ($input['notas'] ?? '')), 0, 500);
        if (mb_strlen($notes) < 10) {
            throw new ValidationException(['notas' => 'Explica la decisión en al menos 10 caracteres.']);
        }
        $paymentId = max(0, (int) ($input['pago_id'] ?? 0));
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'reportes_pago.revisar', $idempotencyKey, [
                'report_id' => $reportId, 'decision' => $decision, 'notes' => $notes, 'payment_id' => $paymentId,
            ], $notes);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            $report = $db->query('SELECT * FROM reportes_pago WHERE id = ? FOR UPDATE', [$reportId])->getRowArray();
            if (!$report) {
                throw PageNotFoundException::forPageNotFound();
            }
            $latest = $db->query('SELECT * FROM reporte_revisiones WHERE reporte_id = ? ORDER BY revision DESC LIMIT 1 FOR UPDATE', [$reportId])->getRowArray();
            $current = (string) ($latest['resultado'] ?? 'PENDIENTE');
            if (in_array($current, ['APROBADO', 'VINCULADO'], true)) {
                throw new ValidationException(['decision' => 'El comprobante ya está conciliado. Revierte el pago por su flujo propio si corresponde.']);
            }
            if ($current === 'RECHAZADO' && $decision !== 'REABIERTO') {
                throw new ValidationException(['decision' => 'Primero reabre el comprobante rechazado.']);
            }
            if ($current !== 'RECHAZADO' && $decision === 'REABIERTO') {
                throw new ValidationException(['decision' => 'Solo puede reabrirse un comprobante rechazado.']);
            }
            if (in_array($decision, ['APROBADO', 'VINCULADO'], true) && $report['cliente_id'] === null) {
                throw new ValidationException(['cliente_id' => 'Identifica al cliente antes de conciliar el comprobante.']);
            }

            $linkedPaymentId = null;
            $receiptId = null;
            if ($decision === 'APROBADO') {
                Access::require($actorId, 'pagos.crear');
                $this->requireActiveClient($db, (int) $report['cliente_id']);
                $account = $db->query('SELECT id, activa FROM cuentas_bancarias WHERE id = ? FOR UPDATE', [$report['cuenta_bancaria_id']])->getRowArray();
                if (!$account || (int) $account['activa'] !== 1) {
                    throw new ValidationException(['decision' => 'La cuenta receptora ya no está activa.']);
                }
                $db->table('pagos')->insert([
                    'cliente_id' => (int) $report['cliente_id'], 'origen' => 'OPERATIVO',
                    'medio_pago' => (string) $report['metodo'], 'importe' => (string) $report['importe_reportado'],
                    'fecha_declarada' => (string) $report['fecha_reportada'], 'operacion_confirmacion_id' => $operation['id'],
                ]);
                $linkedPaymentId = (int) $db->insertID();
                $db->table('pagos_bancarios')->insert([
                    'pago_id' => $linkedPaymentId, 'cuenta_bancaria_id' => (int) $report['cuenta_bancaria_id'],
                    'referencia' => (string) $report['referencia_reportada'],
                    'fecha_bancaria' => (string) $report['fecha_reportada'], 'evidencia_verificacion' => $notes,
                ]);
                $receipt = (new ReceiptService())->issueForPayment($db, $linkedPaymentId, $actorId);
                $receiptId = (int) $receipt['id'];
            } elseif ($decision === 'VINCULADO') {
                $match = $this->requireMatchingPayment($db, $report, $paymentId);
                $linkedPaymentId = (int) $match['id'];
            }

            $revision = ((int) ($latest['revision'] ?? 0)) + 1;
            $db->table('reporte_revisiones')->insert([
                'reporte_id' => $reportId, 'revision' => $revision, 'resultado' => $decision,
                'pago_id' => $linkedPaymentId, 'notas' => $notes, 'operacion_id' => $operation['id'],
            ]);
            $result = ['report_id' => $reportId, 'decision' => $decision, 'payment_id' => $linkedPaymentId, 'receipt_id' => $receiptId];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'reportes_pago.revisar', 'reporte_pago:' . $reportId, [
                'revision' => $revision, 'resultado' => $decision, 'pago_id' => $linkedPaymentId,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo guardar la revisión.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function findFile(int $actorId, int $fileId): array
    {
        Access::require($actorId, 'pagos.ver');
        $file = db_connect()->table('reporte_archivos')->where('id', $fileId)->get()->getRowArray();
        if (!$file) {
            throw PageNotFoundException::forPageNotFound();
        }
        $root = realpath(WRITEPATH . 'uploads');
        $path = realpath(WRITEPATH . 'uploads/' . (string) $file['ruta_relativa']);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $file['absolute_path'] = $path;
        Audit::record($actorId, 'reportes_pago.archivo.descargar', 'reporte_pago:' . (int) $file['reporte_id'], [
            'archivo_id' => $fileId,
        ]);

        return $file;
    }

    /** @return array<string, mixed> */
    private function validateReport(array $input): array
    {
        $errors = [];
        try {
            $amount = Money::decimal(Money::cents((string) ($input['monto'] ?? '')));
        } catch (\InvalidArgumentException) {
            $errors['monto'] = 'Indica un monto válido mayor que cero.';
            $amount = '0.00';
        }
        $date = $this->validDate($input['fecha'] ?? null);
        if ($date === '') {
            $errors['fecha'] = 'Indica una fecha de operación válida.';
        }
        $reference = mb_substr(trim((string) ($input['referencia'] ?? '')), 0, 120);
        if ($reference === '') {
            $errors['referencia'] = 'Indica la referencia bancaria.';
        }
        $method = strtoupper(trim((string) ($input['metodo'] ?? '')));
        if (!in_array($method, ['TRANSFERENCIA', 'DEPOSITO'], true)) {
            $errors['metodo'] = 'Selecciona transferencia o depósito.';
        }
        $bankAccountId = max(0, (int) ($input['cuenta_bancaria_id'] ?? 0));
        if ($bankAccountId < 1) {
            $errors['cuenta_bancaria_id'] = 'Selecciona la cuenta receptora.';
        }
        $clientId = max(0, (int) ($input['cliente_id'] ?? 0));
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'client_id' => $clientId > 0 ? $clientId : null, 'bank_account_id' => $bankAccountId,
            'method' => $method, 'amount' => $amount, 'reported_on' => $date,
            'reference' => $reference, 'notes' => mb_substr(trim((string) ($input['observacion'] ?? '')), 0, 500),
        ];
    }

    /** @return array{name:string,mime:string,extension:string,size:int,hash:string}|null */
    private function validateOptionalUpload(?UploadedFile $file): ?array
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (!$file->isValid()) {
            throw new ValidationException(['attachment' => 'No se pudo recibir el archivo.']);
        }
        $size = $file->getSize();
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw new ValidationException(['attachment' => 'El archivo debe pesar entre 1 byte y 2 MB.']);
        }
        $mime = strtolower($file->getMimeType());
        if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
            throw new ValidationException(['attachment' => 'Formato no permitido. Usa PDF, PNG o JPEG.']);
        }
        $hash = hash_file('sha256', $file->getTempName());
        if (!is_string($hash)) {
            throw new ValidationException(['attachment' => 'No se pudo verificar el archivo.']);
        }
        $name = mb_substr(trim(basename(str_replace('\\', '/', $file->getClientName()))), 0, 190);

        return ['name' => $name ?: 'comprobante.' . self::ALLOWED_MIME_TYPES[$mime], 'mime' => $mime,
            'extension' => self::ALLOWED_MIME_TYPES[$mime], 'size' => $size, 'hash' => $hash];
    }

    private function validDate(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function requireActiveClient(\CodeIgniter\Database\BaseConnection $db, int $clientId): void
    {
        $client = $db->query('SELECT id, activo FROM clientes WHERE id = ? FOR UPDATE', [$clientId])->getRowArray();
        if (!$client || (int) $client['activo'] !== 1) {
            throw new ValidationException(['cliente_id' => 'Selecciona un cliente activo.']);
        }
    }

    private function requireClient(\CodeIgniter\Database\BaseConnection $db, int $clientId): void
    {
        if (!$db->query('SELECT id FROM clientes WHERE id = ? FOR UPDATE', [$clientId])->getRowArray()) {
            throw new ValidationException(['cliente_id' => 'El cliente vinculado ya no existe.']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function matchingPayments(array $report): array
    {
        if ($report['cliente_id'] === null || in_array($report['estado_codigo'], ['APROBADO', 'VINCULADO'], true)) {
            return [];
        }

        return db_connect()->query("SELECT p.id, p.importe, p.fecha_declarada fecha, pb.referencia
            FROM pagos p
            JOIN pagos_bancarios pb ON pb.pago_id = p.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            LEFT JOIN reporte_revisiones rr ON rr.pago_id = p.id AND rr.resultado IN ('APROBADO','VINCULADO')
            WHERE p.cliente_id = ? AND pb.cuenta_bancaria_id = ? AND p.importe = ?
              AND p.fecha_declarada = ? AND pr.pago_id IS NULL AND rr.id IS NULL
            ORDER BY p.id DESC", [(int) $report['cliente_id'], (int) $report['cuenta_bancaria_id'],
                (string) $report['importe_reportado'], (string) $report['fecha_reportada']])->getResultArray();
    }

    /** @return array<string, mixed> */
    private function requireMatchingPayment(\CodeIgniter\Database\BaseConnection $db, array $report, int $paymentId): array
    {
        if ($paymentId < 1) {
            throw new ValidationException(['pago_id' => 'Selecciona el pago existente que corresponde al ingreso.']);
        }
        $payment = $db->query("SELECT p.id FROM pagos p
            JOIN pagos_bancarios pb ON pb.pago_id = p.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            LEFT JOIN reporte_revisiones rr ON rr.pago_id = p.id AND rr.resultado IN ('APROBADO','VINCULADO')
            WHERE p.id = ? AND p.cliente_id = ? AND pb.cuenta_bancaria_id = ? AND p.importe = ?
              AND p.fecha_declarada = ? AND pr.pago_id IS NULL AND rr.id IS NULL FOR UPDATE", [
                $paymentId, (int) $report['cliente_id'], (int) $report['cuenta_bancaria_id'],
                (string) $report['importe_reportado'], (string) $report['fecha_reportada'],
            ])->getRowArray();
        if (!$payment) {
            throw new ValidationException(['pago_id' => 'El pago ya no coincide o fue conciliado por otro comprobante.']);
        }

        return $payment;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'APROBADO' => 'Aprobado', 'VINCULADO' => 'Vinculado', 'RECHAZADO' => 'Rechazado',
            'REABIERTO' => 'Reabierto', default => 'Pendiente',
        };
    }
}
