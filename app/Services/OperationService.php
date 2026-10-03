<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ValidationException;
use CodeIgniter\Database\BaseConnection;

final class OperationService
{
    public function reserve(BaseConnection $db, int $actorId, string $action, string $key, array $payload, ?string $reason = null): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,119}$/D', $key)) {
            throw new ValidationException(['idempotency_key' => 'Usa una clave idempotente de 8 a 120 caracteres.']);
        }
        $hash = hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $existing = $db->query('SELECT * FROM operaciones WHERE clave_idempotencia = ? FOR UPDATE', [$key])->getRowArray();
        if ($existing) {
            if ((int) $existing['usuario_id'] !== $actorId || $existing['accion'] !== $action || !hash_equals($existing['hash_solicitud'], $hash)) {
                throw new ValidationException(['idempotency_key' => 'La clave ya fue usada con una solicitud diferente.']);
            }
            if ($existing['resultado_json'] === null) {
                throw new ValidationException(['idempotency_key' => 'La operación todavía no tiene un resultado recuperable.']);
            }

            return ['id' => (int) $existing['id'], 'result' => json_decode($existing['resultado_json'], true, flags: JSON_THROW_ON_ERROR)];
        }

        $now = gmdate('Y-m-d H:i:s.u');
        $db->table('operaciones')->insert([
            'clave_idempotencia' => $key,
            'hash_solicitud' => $hash,
            'accion' => $action,
            'usuario_id' => $actorId,
            'origen' => 'OPERATIVO',
            'efectiva_en' => $now,
            'registrada_en' => $now,
            'motivo' => $reason,
        ]);

        return ['id' => (int) $db->insertID(), 'result' => null];
    }

    public function complete(BaseConnection $db, int $operationId, array $result): void
    {
        $db->table('operaciones')->where('id', $operationId)->update([
            'resultado_json' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function canonicalize(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
