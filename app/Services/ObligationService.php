<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money;
use App\Domain\ValidationException;

final class ObligationService
{
    /** @return array<string, mixed> */
    public function find(int $obligationId): array
    {
        $db = db_connect();
        $obligation = $db->query("SELECT o.id, o.cliente_id, o.concepto, o.fecha_origen, o.importe_base,
            o.operacion_confirmacion_id, d.tipo, d.numero_completo, d.fecha_emision,
            c.nombre cliente, c.activo cliente_activo
            FROM obligaciones o
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            JOIN clientes c ON c.id = o.cliente_id
            WHERE o.id = ?", [$obligationId])->getRowArray();
        if (!$obligation) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $installments = $db->query("SELECT c.id, c.numero_cuota, cv.version, cv.fecha_vencimiento,
            cv.importe_programado,
            COALESCE(SUM(CASE WHEN ar.aplicacion_id IS NULL AND pr.pago_id IS NULL THEN ap.importe ELSE 0 END), 0) aplicado
            FROM cuotas c
            JOIN cuota_versiones cv ON cv.cuota_id = c.id
              AND cv.version = (SELECT MAX(cv2.version) FROM cuota_versiones cv2 WHERE cv2.cuota_id = c.id)
            LEFT JOIN aplicaciones_pago ap ON ap.cuota_id = c.id
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = ap.pago_id
            WHERE c.obligacion_id = ?
            GROUP BY c.id, c.numero_cuota, cv.version, cv.fecha_vencimiento, cv.importe_programado
            ORDER BY c.numero_cuota", [$obligationId])->getResultArray();

        $today = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $balanceCents = 0;
        $nextDueDate = null;
        foreach ($installments as &$installment) {
            $scheduledCents = Money::cents((string) $installment['importe_programado']);
            $appliedCents = Money::cents((string) $installment['aplicado'], true);
            $installmentBalance = max(0, $scheduledCents - $appliedCents);
            $installment['saldo'] = Money::decimal($installmentBalance);
            $installment['estado'] = $installmentBalance === 0
                ? 'PAGADA'
                : (((string) $installment['fecha_vencimiento']) < $today ? 'VENCIDA' : 'PENDIENTE');
            if ($installmentBalance > 0 && ($nextDueDate === null || $installment['fecha_vencimiento'] < $nextDueDate)) {
                $nextDueDate = $installment['fecha_vencimiento'];
            }
            $balanceCents += $installmentBalance;
        }
        unset($installment);

        $applications = $db->query("SELECT ap.id, ap.cuota_id, ap.importe, c.numero_cuota,
            p.id pago_id, p.fecha_declarada, p.medio_pago,
            ar.aplicacion_id aplicacion_revertida, pr.pago_id pago_revertido
            FROM aplicaciones_pago ap
            JOIN cuotas c ON c.id = ap.cuota_id
            JOIN pagos p ON p.id = ap.pago_id
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            WHERE c.obligacion_id = ?
            ORDER BY p.fecha_declarada DESC, ap.id DESC", [$obligationId])->getResultArray();
        foreach ($applications as &$application) {
            $application['estado'] = $application['aplicacion_revertida'] === null && $application['pago_revertido'] === null
                ? 'VIGENTE'
                : 'REVERTIDA';
        }
        unset($application);

        $versions = $db->query("SELECT cv.cuota_id, c.numero_cuota, cv.version, cv.fecha_vencimiento,
            cv.importe_programado, op.efectiva_en, op.motivo
            FROM cuota_versiones cv
            JOIN cuotas c ON c.id = cv.cuota_id
            JOIN operaciones op ON op.id = cv.operacion_id
            WHERE c.obligacion_id = ?
            ORDER BY cv.version DESC, c.numero_cuota", [$obligationId])->getResultArray();

        $obligation['cuotas'] = $installments;
        $obligation['aplicaciones'] = $applications;
        $obligation['versiones'] = $versions;
        $obligation['saldo'] = Money::decimal($balanceCents);
        $obligation['vencimiento'] = $nextDueDate;
        $obligation['estado'] = $obligation['operacion_confirmacion_id'] === null
            ? 'BORRADOR'
            : ($balanceCents === 0 ? 'PAGADA' : (($nextDueDate ?? $today) < $today ? 'VENCIDA' : 'PENDIENTE'));

        return $obligation;
    }

    /** @return array<string, mixed> */
    public function reprogram(int $actorId, int $obligationId, array $dueDates, string $reason, string $idempotencyKey): array
    {
        Access::require($actorId, 'cartera.reprogramar');
        $reason = trim($reason);
        $errors = [];
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            $errors['reason'] = 'Escribe un motivo de 10 a 500 caracteres.';
        }

        $normalizedDates = [];
        foreach ($dueDates as $installmentId => $date) {
            $id = filter_var($installmentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $date = is_string($date) ? trim($date) : '';
            if ($id === false || !$this->isDate($date)) {
                $errors['due_dates'] = 'Revisa las nuevas fechas del cronograma.';
                continue;
            }
            $normalizedDates[(int) $id] = $date;
        }
        ksort($normalizedDates, SORT_NUMERIC);
        if ($normalizedDates === []) {
            $errors['due_dates'] = 'Indica las nuevas fechas de las cuotas pendientes.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $payload = ['obligation_id' => $obligationId, 'due_dates' => $normalizedDates, 'reason' => $reason];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve(
                $db,
                $actorId,
                'obligaciones.reprogramar',
                $idempotencyKey,
                $payload,
                $reason
            );
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $obligation = $db->query('SELECT id, operacion_confirmacion_id FROM obligaciones WHERE id = ? FOR UPDATE', [$obligationId])->getRowArray();
            if (!$obligation || $obligation['operacion_confirmacion_id'] === null) {
                throw new ValidationException(['obligation' => 'Solo se puede reprogramar una obligación confirmada.']);
            }

            $lockedInstallments = $db->query(
                'SELECT id, numero_cuota FROM cuotas WHERE obligacion_id = ? ORDER BY numero_cuota FOR UPDATE',
                [$obligationId]
            )->getResultArray();
            if ($lockedInstallments === []) {
                throw new ValidationException(['obligation' => 'La obligación no tiene un cronograma confirmado.']);
            }

            $rows = $db->query("SELECT c.id, c.numero_cuota, cv.version, cv.fecha_vencimiento, cv.importe_programado,
                COALESCE(SUM(CASE WHEN ar.aplicacion_id IS NULL AND pr.pago_id IS NULL THEN ap.importe ELSE 0 END), 0) aplicado
                FROM cuotas c
                JOIN cuota_versiones cv ON cv.cuota_id = c.id
                  AND cv.version = (SELECT MAX(cv2.version) FROM cuota_versiones cv2 WHERE cv2.cuota_id = c.id)
                LEFT JOIN aplicaciones_pago ap ON ap.cuota_id = c.id
                LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
                LEFT JOIN pago_reversiones pr ON pr.pago_id = ap.pago_id
                WHERE c.obligacion_id = ?
                GROUP BY c.id, c.numero_cuota, cv.version, cv.fecha_vencimiento, cv.importe_programado
                ORDER BY c.numero_cuota", [$obligationId])->getResultArray();

            $pending = [];
            foreach ($rows as $row) {
                if (Money::cents((string) $row['importe_programado']) > Money::cents((string) $row['aplicado'], true)) {
                    $pending[(int) $row['id']] = $row;
                }
            }
            ksort($pending, SORT_NUMERIC);
            if ($pending === []) {
                throw new ValidationException(['obligation' => 'La obligación no tiene cuotas pendientes para reprogramar.']);
            }
            if (array_keys($normalizedDates) !== array_keys($pending)) {
                throw new ValidationException(['due_dates' => 'Debes indicar una fecha para cada cuota pendiente.']);
            }

            $today = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
            $changed = false;
            $previousDate = null;
            foreach ($pending as $installmentId => $row) {
                $newDate = $normalizedDates[$installmentId];
                if ($newDate < $today) {
                    throw new ValidationException(['due_dates' => 'Las nuevas fechas no pueden ser anteriores a hoy.']);
                }
                if ($previousDate !== null && $newDate < $previousDate) {
                    throw new ValidationException(['due_dates' => 'Las fechas deben conservar el orden de las cuotas.']);
                }
                $previousDate = $newDate;
                $changed = $changed || $newDate !== $row['fecha_vencimiento'];
            }
            if (!$changed) {
                throw new ValidationException(['due_dates' => 'Cambia al menos una fecha para guardar la reprogramación.']);
            }

            foreach ($pending as $installmentId => $row) {
                $db->table('cuota_versiones')->insert([
                    'cuota_id' => $installmentId,
                    'version' => (int) $row['version'] + 1,
                    'fecha_vencimiento' => $normalizedDates[$installmentId],
                    'importe_programado' => $row['importe_programado'],
                    'operacion_id' => $operation['id'],
                ]);
            }

            $result = [
                'operation_id' => (int) $operation['id'],
                'obligation_id' => $obligationId,
                'installments_updated' => count($pending),
                'status' => 'REPROGRAMADA',
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'obligaciones.reprogramar', 'obligacion:' . $obligationId, [
                'motivo' => $reason,
                'cuotas_actualizadas' => count($pending),
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo guardar la reprogramación.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
