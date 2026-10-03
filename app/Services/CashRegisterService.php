<?php

declare(strict_types=1);
namespace App\Services;

use App\Domain\ValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;

final class CashRegisterService
{
    public function all(int $actorId): array
    {
        Access::require($actorId, 'caja.gestionar');
        return db_connect()->table('cajas')->orderBy('codigo')->get()->getResultArray();
    }

    public function find(int $actorId, int $id): array
    {
        Access::require($actorId, 'caja.gestionar');
        $row = db_connect()->table('cajas')->where('id', $id)->get()->getRowArray();
        if (!$row) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $row;
    }

    public function save(int $actorId, array $input, ?int $id = null): int
    {
        Access::require($actorId, 'caja.gestionar');
        $code = strtoupper(trim(is_string($input['codigo'] ?? null) ? $input['codigo'] : ''));
        $name = trim(is_string($input['nombre'] ?? null) ? $input['nombre'] : '');
        $active = $input['activa'] ?? null;
        $version = $input['version'] ?? null;
        $errors = [];
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,29}$/D', $code)) {
            $errors['codigo'] = 'Usa un código de 1 a 30 letras, números, guiones o guiones bajos; comienza con una letra o número.';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors['nombre'] = 'Escribe un nombre de 2 a 120 caracteres.';
        }
        if (!in_array($active, ['0', '1'], true)) {
            $errors['activa'] = 'Selecciona un estado válido.';
        }
        if ($id !== null && ((!is_string($version) && !is_int($version)) || filter_var($version, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            $errors['version'] = 'Vuelve a abrir la caja antes de editarla.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $now = gmdate('Y-m-d H:i:s');
            $data = ['codigo' => $code, 'nombre' => $name, 'activa' => (int) $active, 'actualizado_en' => $now];
            $before = null;
            if ($id === null) {
                $db->table('cajas')->insert($data + ['version' => 1, 'creado_en' => $now]);
                $id = (int) $db->insertID();
                $action = 'cajas.crear';
            } else {
                $before = $db->query('SELECT * FROM cajas WHERE id = ? FOR UPDATE', [$id])->getRowArray();
                if (!$before) {
                    throw PageNotFoundException::forPageNotFound();
                }
                if ((int) $before['version'] !== (int) $version) {
                    throw new ValidationException(['version' => 'La caja cambió desde que abriste el formulario. Vuelve al listado y revisa los datos actuales.']);
                }
                if ((int) $active === 0 && $db->query("SELECT id FROM turnos_caja WHERE caja_abierta_id = ? AND estado = 'ABIERTO' FOR UPDATE", [$id])->getRowArray()) {
                    throw new ValidationException(['activa' => 'No puedes desactivar una caja mientras tenga un turno abierto.']);
                }
                $db->table('cajas')->where('id', $id)->update($data + ['version' => (int) $version + 1]);
                $action = 'cajas.editar';
            }
            Audit::record($actorId, $action, 'caja:' . $id, [
                'anterior' => $before === null ? null : array_intersect_key($before, array_flip(['codigo', 'nombre', 'activa', 'version'])),
                'codigo' => $code, 'nombre' => $name, 'activa' => (int) $active,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo guardar la caja.');
            }
            return $id;
        } catch (\Throwable $error) {
            $db->transRollback();
            if ((int) $error->getCode() === 1062) {
                throw new ValidationException(['codigo' => 'Ya existe una caja con ese código. Edítala o utiliza otro código.']);
            }
            throw $error;
        }
    }
}
