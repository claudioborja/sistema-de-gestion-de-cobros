<?php
declare(strict_types=1);
namespace App\Services;

use App\Domain\ValidationException;

final class BankAccountService
{
    public function all(int $actorId): array
    {
        Access::require($actorId, 'configuracion.gestionar');
        return db_connect()->table('cuentas_bancarias')->orderBy('activa', 'DESC')->orderBy('institucion')->orderBy('alias')->get()->getResultArray();
    }

    public function find(int $actorId, int $id): array
    {
        Access::require($actorId, 'configuracion.gestionar');
        $row = db_connect()->table('cuentas_bancarias')->where('id', $id)->get()->getRowArray();
        if (!$row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $row['used'] = db_connect()->table('pagos_bancarios')->where('cuenta_bancaria_id', $id)->countAllResults() > 0;
        return $row;
    }

    public function save(int $actorId, ?int $id, array $input): int
    {
        Access::require($actorId, 'configuracion.gestionar');
        $errors = [];
        $data = [];
        foreach (['institucion' => 120, 'numero_cuenta' => 80, 'alias' => 100, 'titular' => 120] as $key => $limit) {
            $value = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
            if ($value === '' || mb_strlen($value) > $limit) {
                $errors[$key] = 'Completa este dato con un máximo de ' . $limit . ' caracteres.';
            }
            $data[$key] = $value;
        }
        $data['tipo'] = is_string($input['tipo'] ?? null) ? $input['tipo'] : '';
        if (!in_array($data['tipo'], ['AHORROS', 'CORRIENTE', 'OTRA'], true)) {
            $errors['tipo'] = 'Selecciona un tipo de cuenta válido.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            if ($id !== null) {
                $existing = $db->query('SELECT * FROM cuentas_bancarias WHERE id = ? FOR UPDATE', [$id])->getRowArray();
                if (!$existing) {
                    throw new ValidationException(['account' => 'La cuenta no existe.']);
                }
                $used = $db->table('pagos_bancarios')->where('cuenta_bancaria_id', $id)->countAllResults() > 0;
                if ($used && ($existing['institucion'] !== $data['institucion'] || $existing['numero_cuenta'] !== $data['numero_cuenta'])) {
                    throw new ValidationException(['numero_cuenta' => 'Esta cuenta tiene cobros. Conserva el banco y el número; registra otra cuenta si cambiaron.']);
                }
                $db->table('cuentas_bancarias')->where('id', $id)->update($data);
            } else {
                $db->table('cuentas_bancarias')->insert($data + ['activa' => 1]);
                $id = (int) $db->insertID();
            }
            Audit::record($actorId, 'cuentas_bancarias.guardar', 'cuenta:' . $id);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo guardar la cuenta bancaria.');
            }
            return $id;
        } catch (\Throwable $error) {
            $db->transRollback();
            if ((int) $error->getCode() === 1062) {
                throw new ValidationException(['numero_cuenta' => 'Ya existe una cuenta con este banco y número. Puedes editarla o reactivarla.']);
            }
            throw $error;
        }
    }

    public function setActive(int $actorId, int $id, mixed $active): void
    {
        Access::require($actorId, 'configuracion.gestionar');
        if (!in_array($active, ['0', '1'], true)) {
            throw new ValidationException(['activa' => 'El estado de la cuenta no es válido.']);
        }
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            if (!$db->query('SELECT id FROM cuentas_bancarias WHERE id = ? FOR UPDATE', [$id])->getRowArray()) {
                throw new ValidationException(['account' => 'La cuenta no existe.']);
            }
            $db->table('cuentas_bancarias')->where('id', $id)->update(['activa' => (int) $active]);
            Audit::record($actorId, 'cuentas_bancarias.estado', 'cuenta:' . $id, ['activa' => (int) $active]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo cambiar el estado de la cuenta.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }
}
