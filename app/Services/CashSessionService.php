<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money;
use App\Domain\ValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;

final class CashSessionService
{
    /** @return array<string, mixed> */
    public function dashboard(int $actorId): array
    {
        Access::require($actorId, 'caja.operar');
        $db = db_connect();
        $active = $db->query("SELECT t.id, t.caja_id, t.fondo_inicial, t.estado, t.abierto_en,
            c.codigo caja_codigo, c.nombre caja_nombre,
            COALESCE(SUM(CASE WHEN m.tipo IN ('APORTE','COBRO') THEN m.importe ELSE 0 END), 0) entradas,
            COALESCE(SUM(CASE WHEN m.tipo IN ('RETIRO','GASTO','DEVOLUCION') THEN m.importe ELSE 0 END), 0) salidas,
            COALESCE(SUM(CASE WHEN m.tipo = 'COBRO' THEN m.importe ELSE 0 END), 0) cobros_efectivo,
            COUNT(m.id) movimientos_total
            FROM turnos_caja t
            JOIN cajas c ON c.id = t.caja_id
            LEFT JOIN movimientos_caja m ON m.turno_id = t.id
            WHERE t.usuario_abierto_id = ? AND t.estado = 'ABIERTO'
            GROUP BY t.id, t.caja_id, t.fondo_inicial, t.estado, t.abierto_en, c.codigo, c.nombre", [$actorId])->getRowArray();

        if ($active) {
            $expected = Money::cents((string) $active['fondo_inicial'], true)
                + Money::cents((string) $active['entradas'], true)
                - Money::cents((string) $active['salidas'], true);
            $active['fondo_inicial'] = Money::decimal(Money::cents((string) $active['fondo_inicial'], true));
            $active['entradas'] = Money::decimal(Money::cents((string) $active['entradas'], true));
            $active['salidas'] = Money::decimal(Money::cents((string) $active['salidas'], true));
            $active['cobros_efectivo'] = Money::decimal(Money::cents((string) $active['cobros_efectivo'], true));
            $active['efectivo_esperado'] = Money::decimal($expected);
            $active['abierto_en_local'] = $this->localDateTime((string) $active['abierto_en']);
            $active['movimientos_total'] = (int) $active['movimientos_total'];
        }

        $registers = [];
        if (!$active) {
            $registers = $db->query("SELECT c.id, c.codigo, c.nombre
                FROM cajas c
                LEFT JOIN turnos_caja t ON t.caja_abierta_id = c.id AND t.estado = 'ABIERTO'
                WHERE c.activa = 1 AND t.id IS NULL
                ORDER BY c.codigo, c.id")->getResultArray();
        }

        $movements = $active ? $db->query("SELECT id, tipo, importe, concepto, pago_id, registrado_en
            FROM movimientos_caja WHERE turno_id = ? ORDER BY registrado_en DESC, id DESC LIMIT 25", [(int) $active['id']])->getResultArray() : [];
        foreach ($movements as &$movement) {
            $movement['importe'] = Money::decimal(Money::cents((string) $movement['importe'], true));
            $movement['registrado_en_local'] = $this->localDateTime((string) $movement['registrado_en']);
        }
        unset($movement);

        $incoming = $db->query("SELECT e.id entrega_id, e.turno_id, e.importe_entregado, e.fondo_remanente, e.entregado_en,
            c.codigo caja_codigo, c.nombre caja_nombre, u.username entregado_por_nombre
            FROM entregas_turno e
            JOIN turnos_caja t ON t.id = e.turno_id
            JOIN cajas c ON c.id = t.caja_id
            JOIN users u ON u.id = e.entregado_por
            WHERE e.recibido_por = ? AND e.estado = 'PENDIENTE'
            ORDER BY e.entregado_en, e.id", [$actorId])->getResultArray();
        foreach ($incoming as &$handoff) {
            $handoff['importe_entregado'] = Money::decimal(Money::cents((string) $handoff['importe_entregado'], true));
            $handoff['fondo_remanente'] = Money::decimal(Money::cents((string) $handoff['fondo_remanente'], true));
            $handoff['entregado_en_local'] = $this->localDateTime((string) $handoff['entregado_en']);
        }
        unset($handoff);

        $history = $db->query("SELECT t.id, t.fondo_inicial, t.efectivo_contado, t.diferencia, t.abierto_en, t.cerrado_en,
            c.codigo caja_codigo, c.nombre caja_nombre, e.id entrega_id, e.estado entrega_estado,
            e.fondo_remanente, receptor.username recibido_por_nombre
            FROM turnos_caja t JOIN cajas c ON c.id = t.caja_id
            LEFT JOIN entregas_turno e ON e.turno_id = t.id
            LEFT JOIN users receptor ON receptor.id = e.recibido_por
            WHERE t.usuario_id = ? AND t.estado = 'CERRADO'
            ORDER BY t.cerrado_en DESC, t.id DESC LIMIT 5", [$actorId])->getResultArray();
        foreach ($history as &$shift) {
            foreach (['fondo_inicial', 'efectivo_contado'] as $field) {
                $shift[$field] = Money::decimal(Money::cents((string) $shift[$field], true));
            }
            $shift['diferencia'] = number_format((float) $shift['diferencia'], 2, '.', '');
            $shift['abierto_en_local'] = $this->localDateTime((string) $shift['abierto_en']);
            $shift['cerrado_en_local'] = $this->localDateTime((string) $shift['cerrado_en']);
            $shift['fondo_remanente'] = $shift['fondo_remanente'] === null
                ? null
                : Money::decimal(Money::cents((string) $shift['fondo_remanente'], true));
        }
        unset($shift);

        return compact('active', 'registers', 'movements', 'incoming', 'history');
    }

    /** @return array<string, mixed> */
    public function open(int $actorId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'caja.operar');
        Access::require($actorId, 'caja.abrir');
        $registerId = filter_var($input['caja_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $errors = [];
        if ($registerId === false) {
            $errors['caja_id'] = 'Selecciona una caja disponible.';
        }
        try {
            $openingCents = Money::cents(is_string($input['fondo_inicial'] ?? null) ? $input['fondo_inicial'] : '', true);
        } catch (\InvalidArgumentException) {
            $openingCents = 0;
            $errors['fondo_inicial'] = 'Escribe un fondo inicial válido con máximo dos decimales.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $opening = Money::decimal($openingCents);
        $payload = ['caja_id' => (int) $registerId, 'fondo_inicial' => $opening];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'caja.turno.abrir', $idempotencyKey, $payload);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $register = $db->query('SELECT id, codigo, nombre, activa FROM cajas WHERE id = ? FOR UPDATE', [(int) $registerId])->getRowArray();
            if (!$register || (int) $register['activa'] !== 1) {
                throw new ValidationException(['caja_id' => 'La caja seleccionada no está activa.']);
            }
            if ($db->query("SELECT id FROM turnos_caja WHERE estado = 'ABIERTO' AND (caja_abierta_id = ? OR usuario_abierto_id = ?) FOR UPDATE", [(int) $registerId, $actorId])->getRowArray()) {
                throw new ValidationException(['caja_id' => 'La caja o tu usuario ya tiene un turno abierto. Actualiza la pantalla y revisa el turno vigente.']);
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $db->table('turnos_caja')->insert([
                'caja_id' => (int) $registerId,
                'usuario_id' => $actorId,
                'fondo_inicial' => $opening,
                'estado' => 'ABIERTO',
                'abierto_en' => $now,
                'operacion_apertura_id' => (int) $operation['id'],
                'caja_abierta_id' => (int) $registerId,
                'usuario_abierto_id' => $actorId,
            ]);
            $shiftId = (int) $db->insertID();
            $result = [
                'operation_id' => (int) $operation['id'],
                'shift_id' => $shiftId,
                'register_id' => (int) $registerId,
                'register_code' => (string) $register['codigo'],
                'opening_fund' => $opening,
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'caja.turno.abrir', 'turno_caja:' . $shiftId, [
                'caja_id' => (int) $registerId,
                'fondo_inicial' => $opening,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo abrir el turno de caja.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            if ((int) $error->getCode() === 1062) {
                throw new ValidationException(['caja_id' => 'La caja o tu usuario ya tiene un turno abierto. Actualiza la pantalla.']);
            }
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function movementContext(int $actorId, int $shiftId): array
    {
        Access::require($actorId, 'caja.movimientos');
        $shift = db_connect()->query("SELECT t.id, t.fondo_inicial, t.abierto_en, c.codigo caja_codigo, c.nombre caja_nombre,
            COALESCE(SUM(CASE WHEN m.tipo IN ('APORTE','COBRO') THEN m.importe ELSE 0 END), 0) entradas,
            COALESCE(SUM(CASE WHEN m.tipo IN ('RETIRO','GASTO','DEVOLUCION') THEN m.importe ELSE 0 END), 0) salidas
            FROM turnos_caja t
            JOIN cajas c ON c.id = t.caja_id
            LEFT JOIN movimientos_caja m ON m.turno_id = t.id
            WHERE t.id = ? AND t.usuario_id = ? AND t.usuario_abierto_id = ? AND t.estado = 'ABIERTO'
            GROUP BY t.id, t.fondo_inicial, t.abierto_en, c.codigo, c.nombre", [$shiftId, $actorId, $actorId])->getRowArray();
        if (!$shift) {
            throw PageNotFoundException::forPageNotFound();
        }

        $expectedCents = Money::cents((string) $shift['fondo_inicial'], true)
            + Money::cents((string) $shift['entradas'], true)
            - Money::cents((string) $shift['salidas'], true);
        $shift['fondo_inicial'] = Money::decimal(Money::cents((string) $shift['fondo_inicial'], true));
        $shift['efectivo_esperado'] = Money::decimal($expectedCents);
        $shift['abierto_en_local'] = $this->localDateTime((string) $shift['abierto_en']);

        return $shift;
    }

    /** @return array<string, mixed> */
    public function addMovement(int $actorId, int $shiftId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'caja.movimientos');
        $type = is_string($input['tipo'] ?? null) ? strtoupper(trim($input['tipo'])) : '';
        $concept = trim(is_string($input['concepto'] ?? null) ? $input['concepto'] : '');
        $errors = [];
        if (!in_array($type, ['APORTE', 'RETIRO', 'GASTO'], true)) {
            $errors['tipo'] = 'Selecciona aporte, retiro o gasto.';
        }
        try {
            $amountCents = Money::cents(is_string($input['importe'] ?? null) ? $input['importe'] : '');
        } catch (\InvalidArgumentException) {
            $amountCents = 0;
            $errors['importe'] = 'Escribe un importe mayor que cero con máximo dos decimales.';
        }
        if (mb_strlen($concept) < 5 || mb_strlen($concept) > 300) {
            $errors['concepto'] = 'Explica el movimiento en 5 a 300 caracteres.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $amount = Money::decimal($amountCents);
        $payload = ['shift_id' => $shiftId, 'type' => $type, 'amount' => $amount, 'concept' => $concept];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'caja.movimiento.registrar', $idempotencyKey, $payload, $concept);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $shift = $db->query("SELECT id, fondo_inicial FROM turnos_caja
                WHERE id = ? AND usuario_id = ? AND usuario_abierto_id = ? AND estado = 'ABIERTO' FOR UPDATE", [$shiftId, $actorId, $actorId])->getRowArray();
            if (!$shift) {
                throw new ValidationException(['turno' => 'El turno ya no está abierto o no pertenece a tu usuario. Vuelve a Mi caja.']);
            }
            $totals = $db->query("SELECT
                COALESCE(SUM(CASE WHEN tipo IN ('APORTE','COBRO') THEN importe ELSE 0 END), 0) entradas,
                COALESCE(SUM(CASE WHEN tipo IN ('RETIRO','GASTO','DEVOLUCION') THEN importe ELSE 0 END), 0) salidas
                FROM movimientos_caja WHERE turno_id = ?", [$shiftId])->getRowArray();
            $expectedCents = Money::cents((string) $shift['fondo_inicial'], true)
                + Money::cents((string) $totals['entradas'], true)
                - Money::cents((string) $totals['salidas'], true);
            if (in_array($type, ['RETIRO', 'GASTO'], true) && $amountCents > $expectedCents) {
                throw new ValidationException(['importe' => 'El importe supera el efectivo esperado de $' . Money::decimal($expectedCents) . '.']);
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $db->table('movimientos_caja')->insert([
                'turno_id' => $shiftId,
                'tipo' => $type,
                'importe' => $amount,
                'concepto' => $concept,
                'operacion_id' => (int) $operation['id'],
                'registrado_por' => $actorId,
                'registrado_en' => $now,
            ]);
            $movementId = (int) $db->insertID();
            $expectedAfter = $type === 'APORTE' ? $expectedCents + $amountCents : $expectedCents - $amountCents;
            $result = [
                'operation_id' => (int) $operation['id'],
                'movement_id' => $movementId,
                'shift_id' => $shiftId,
                'type' => $type,
                'amount' => $amount,
                'expected_after' => Money::decimal($expectedAfter),
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'caja.movimiento.registrar', 'movimiento_caja:' . $movementId, [
                'turno_id' => $shiftId,
                'tipo' => $type,
                'importe' => $amount,
                'efectivo_esperado' => $result['expected_after'],
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo registrar el movimiento de caja.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function closingContext(int $actorId, int $shiftId): array
    {
        Access::require($actorId, 'caja.cerrar');
        $shift = db_connect()->query("SELECT t.id, t.fondo_inicial, t.abierto_en, c.codigo caja_codigo, c.nombre caja_nombre,
            COALESCE(SUM(CASE WHEN m.tipo IN ('APORTE','COBRO') THEN m.importe ELSE 0 END), 0) entradas,
            COALESCE(SUM(CASE WHEN m.tipo IN ('RETIRO','GASTO','DEVOLUCION') THEN m.importe ELSE 0 END), 0) salidas,
            COUNT(m.id) movimientos_total
            FROM turnos_caja t
            JOIN cajas c ON c.id = t.caja_id
            LEFT JOIN movimientos_caja m ON m.turno_id = t.id
            WHERE t.id = ? AND t.usuario_id = ? AND t.usuario_abierto_id = ? AND t.estado = 'ABIERTO'
            GROUP BY t.id, t.fondo_inicial, t.abierto_en, c.codigo, c.nombre", [$shiftId, $actorId, $actorId])->getRowArray();
        if (!$shift) {
            throw PageNotFoundException::forPageNotFound();
        }

        $expectedCents = Money::cents((string) $shift['fondo_inicial'], true)
            + Money::cents((string) $shift['entradas'], true)
            - Money::cents((string) $shift['salidas'], true);
        foreach (['fondo_inicial', 'entradas', 'salidas'] as $field) {
            $shift[$field] = Money::decimal(Money::cents((string) $shift[$field], true));
        }
        $shift['efectivo_esperado'] = Money::decimal($expectedCents);
        $shift['abierto_en_local'] = $this->localDateTime((string) $shift['abierto_en']);
        $shift['movimientos_total'] = (int) $shift['movimientos_total'];

        return $shift;
    }

    /** @return array<string, mixed> */
    public function close(int $actorId, int $shiftId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'caja.cerrar');
        $reason = trim(is_string($input['motivo_diferencia'] ?? null) ? $input['motivo_diferencia'] : '');
        $errors = [];
        try {
            $countedCents = Money::cents(is_string($input['efectivo_contado'] ?? null) ? $input['efectivo_contado'] : '', true);
        } catch (\InvalidArgumentException) {
            $countedCents = 0;
            $errors['efectivo_contado'] = 'Escribe el efectivo contado con máximo dos decimales.';
        }
        if (mb_strlen($reason) > 500) {
            $errors['motivo_diferencia'] = 'La explicación no puede superar 500 caracteres.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $counted = Money::decimal($countedCents);
        $payload = ['shift_id' => $shiftId, 'counted' => $counted, 'difference_reason' => $reason];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'caja.turno.cerrar', $idempotencyKey, $payload, $reason === '' ? null : $reason);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $shift = $db->query("SELECT id, fondo_inicial FROM turnos_caja
                WHERE id = ? AND usuario_id = ? AND usuario_abierto_id = ? AND estado = 'ABIERTO' FOR UPDATE", [$shiftId, $actorId, $actorId])->getRowArray();
            if (!$shift) {
                throw new ValidationException(['turno' => 'El turno ya fue cerrado o no pertenece a tu usuario. Vuelve a Mi caja.']);
            }
            $totals = $db->query("SELECT
                COALESCE(SUM(CASE WHEN tipo IN ('APORTE','COBRO') THEN importe ELSE 0 END), 0) entradas,
                COALESCE(SUM(CASE WHEN tipo IN ('RETIRO','GASTO','DEVOLUCION') THEN importe ELSE 0 END), 0) salidas
                FROM movimientos_caja WHERE turno_id = ?", [$shiftId])->getRowArray();
            $expectedCents = Money::cents((string) $shift['fondo_inicial'], true)
                + Money::cents((string) $totals['entradas'], true)
                - Money::cents((string) $totals['salidas'], true);
            $differenceCents = $countedCents - $expectedCents;
            if ($differenceCents !== 0 && mb_strlen($reason) < 5) {
                throw new ValidationException(['motivo_diferencia' => 'Explica el faltante o sobrante en al menos 5 caracteres.']);
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $difference = $this->signedDecimal($differenceCents);
            $db->table('turnos_caja')->where('id', $shiftId)->update([
                'estado' => 'CERRADO',
                'cerrado_en' => $now,
                'efectivo_contado' => $counted,
                'diferencia' => $difference,
                'motivo_diferencia' => $differenceCents === 0 ? null : $reason,
                'operacion_cierre_id' => (int) $operation['id'],
                'caja_abierta_id' => null,
                'usuario_abierto_id' => null,
            ]);
            $result = [
                'operation_id' => (int) $operation['id'],
                'shift_id' => $shiftId,
                'expected' => Money::decimal($expectedCents),
                'counted' => $counted,
                'difference' => $difference,
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'caja.turno.cerrar', 'turno_caja:' . $shiftId, [
                'efectivo_esperado' => $result['expected'],
                'efectivo_contado' => $counted,
                'diferencia' => $difference,
                'motivo_diferencia' => $differenceCents === 0 ? null : $reason,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo cerrar el turno de caja.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function handoffContext(int $actorId, int $shiftId): array
    {
        Access::require($actorId, 'caja.entregar');
        $shift = db_connect()->query("SELECT t.id, t.usuario_id, t.fondo_inicial, t.efectivo_contado, t.diferencia,
            t.abierto_en, t.cerrado_en, c.codigo caja_codigo, c.nombre caja_nombre,
            propietario.username propietario_nombre,
            e.id entrega_id, e.importe_entregado, e.fondo_remanente, e.estado entrega_estado,
            e.observacion_entrega, e.observacion_recepcion, e.entregado_en, e.recibido_en,
            e.entregado_por, e.recibido_por, emisor.username entregado_por_nombre,
            receptor.username recibido_por_nombre
            FROM turnos_caja t
            JOIN cajas c ON c.id = t.caja_id
            JOIN users propietario ON propietario.id = t.usuario_id
            LEFT JOIN entregas_turno e ON e.turno_id = t.id
            LEFT JOIN users emisor ON emisor.id = e.entregado_por
            LEFT JOIN users receptor ON receptor.id = e.recibido_por
            WHERE t.id = ? AND t.estado = 'CERRADO'", [$shiftId])->getRowArray();
        if (!$shift) {
            throw PageNotFoundException::forPageNotFound();
        }

        $isOwner = (int) $shift['usuario_id'] === $actorId;
        $isReceiver = $shift['recibido_por'] !== null && (int) $shift['recibido_por'] === $actorId;
        if (!$isOwner && !$isReceiver && !Access::can($actorId, 'caja.gestionar')) {
            throw PageNotFoundException::forPageNotFound();
        }

        foreach (['fondo_inicial', 'efectivo_contado'] as $field) {
            $shift[$field] = Money::decimal(Money::cents((string) $shift[$field], true));
        }
        $shift['diferencia'] = $this->signedDecimal($this->signedCents((string) $shift['diferencia']));
        if ($shift['entrega_id'] !== null) {
            $shift['importe_entregado'] = Money::decimal(Money::cents((string) $shift['importe_entregado'], true));
            $shift['fondo_remanente'] = Money::decimal(Money::cents((string) $shift['fondo_remanente'], true));
            $shift['entregado_en_local'] = $this->localDateTime((string) $shift['entregado_en']);
            $shift['recibido_en_local'] = $shift['recibido_en'] === null ? null : $this->localDateTime((string) $shift['recibido_en']);
        }
        $shift['abierto_en_local'] = $this->localDateTime((string) $shift['abierto_en']);
        $shift['cerrado_en_local'] = $this->localDateTime((string) $shift['cerrado_en']);
        $shift['can_deliver'] = $shift['entrega_id'] === null && $isOwner;
        $shift['can_receive'] = $shift['entrega_estado'] === 'PENDIENTE' && $isReceiver;
        $shift['recipients'] = $shift['can_deliver'] ? $this->recipientCandidates($actorId) : [];

        return $shift;
    }

    /** @return array<string, mixed> */
    public function deliver(int $actorId, int $shiftId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'caja.entregar');
        $receiverId = filter_var($input['recibido_por'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $note = trim(is_string($input['observacion_entrega'] ?? null) ? $input['observacion_entrega'] : '');
        $errors = [];
        if ($receiverId === false || (int) $receiverId === $actorId) {
            $errors['recibido_por'] = 'Selecciona a otra persona autorizada para recibir el efectivo.';
        } elseif (!in_array((int) $receiverId, array_column($this->recipientCandidates($actorId), 'id'), true)) {
            $errors['recibido_por'] = 'La persona seleccionada no está activa o no puede recibir turnos.';
        }
        try {
            $remainderCents = Money::cents(is_string($input['fondo_remanente'] ?? null) ? $input['fondo_remanente'] : '', true);
        } catch (\InvalidArgumentException) {
            $remainderCents = 0;
            $errors['fondo_remanente'] = 'Escribe un fondo remanente válido con máximo dos decimales.';
        }
        if (mb_strlen($note) > 500) {
            $errors['observacion_entrega'] = 'La observación no puede superar 500 caracteres.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $remainder = Money::decimal($remainderCents);
        $payload = ['shift_id' => $shiftId, 'receiver_id' => (int) $receiverId, 'remainder' => $remainder, 'note' => $note];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'caja.turno.entregar', $idempotencyKey, $payload, $note === '' ? null : $note);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $shift = $db->query("SELECT id, efectivo_contado FROM turnos_caja
                WHERE id = ? AND usuario_id = ? AND estado = 'CERRADO' FOR UPDATE", [$shiftId, $actorId])->getRowArray();
            if (!$shift) {
                throw new ValidationException(['turno' => 'El turno no está cerrado o no pertenece a tu usuario.']);
            }
            if ($db->query('SELECT id FROM entregas_turno WHERE turno_id = ? FOR UPDATE', [$shiftId])->getRowArray()) {
                throw new ValidationException(['turno' => 'Este turno ya tiene una entrega registrada.']);
            }
            $countedCents = Money::cents((string) $shift['efectivo_contado'], true);
            if ($remainderCents > $countedCents) {
                throw new ValidationException(['fondo_remanente' => 'El fondo remanente no puede superar el efectivo contado de $' . Money::decimal($countedCents) . '.']);
            }

            $delivered = Money::decimal($countedCents - $remainderCents);
            $now = gmdate('Y-m-d H:i:s.u');
            $db->table('entregas_turno')->insert([
                'turno_id' => $shiftId,
                'entregado_por' => $actorId,
                'recibido_por' => (int) $receiverId,
                'importe_entregado' => $delivered,
                'fondo_remanente' => $remainder,
                'estado' => 'PENDIENTE',
                'observacion_entrega' => $note === '' ? null : $note,
                'operacion_entrega_id' => (int) $operation['id'],
                'entregado_en' => $now,
            ]);
            $handoffId = (int) $db->insertID();
            $result = [
                'operation_id' => (int) $operation['id'],
                'handoff_id' => $handoffId,
                'shift_id' => $shiftId,
                'receiver_id' => (int) $receiverId,
                'delivered' => $delivered,
                'remainder' => $remainder,
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'caja.turno.entregar', 'entrega_turno:' . $handoffId, [
                'turno_id' => $shiftId,
                'recibido_por' => (int) $receiverId,
                'importe_entregado' => $delivered,
                'fondo_remanente' => $remainder,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo registrar la entrega del turno.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            if ((int) $error->getCode() === 1062) {
                throw new ValidationException(['turno' => 'Este turno ya tiene una entrega registrada.']);
            }
            throw $error;
        }
    }

    /** @return array<string, mixed> */
    public function receive(int $actorId, int $shiftId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'caja.entregar');
        $note = trim(is_string($input['observacion_recepcion'] ?? null) ? $input['observacion_recepcion'] : '');
        if (mb_strlen($note) > 500) {
            throw new ValidationException(['observacion_recepcion' => 'La observación no puede superar 500 caracteres.']);
        }

        $payload = ['shift_id' => $shiftId, 'note' => $note];
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();
        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'caja.turno.recibir', $idempotencyKey, $payload, $note === '' ? null : $note);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }

            $handoff = $db->query("SELECT id, importe_entregado, fondo_remanente FROM entregas_turno
                WHERE turno_id = ? AND recibido_por = ? AND estado = 'PENDIENTE' FOR UPDATE", [$shiftId, $actorId])->getRowArray();
            if (!$handoff) {
                throw new ValidationException(['turno' => 'La entrega ya fue recibida o no está asignada a tu usuario.']);
            }

            $now = gmdate('Y-m-d H:i:s.u');
            $db->table('entregas_turno')->where('id', (int) $handoff['id'])->update([
                'estado' => 'RECIBIDA',
                'observacion_recepcion' => $note === '' ? null : $note,
                'operacion_recepcion_id' => (int) $operation['id'],
                'recibido_en' => $now,
            ]);
            $result = [
                'operation_id' => (int) $operation['id'],
                'handoff_id' => (int) $handoff['id'],
                'shift_id' => $shiftId,
                'delivered' => Money::decimal(Money::cents((string) $handoff['importe_entregado'], true)),
                'remainder' => Money::decimal(Money::cents((string) $handoff['fondo_remanente'], true)),
            ];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'caja.turno.recibir', 'entrega_turno:' . (int) $handoff['id'], [
                'turno_id' => $shiftId,
                'importe_entregado' => $result['delivered'],
                'fondo_remanente' => $result['remainder'],
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo confirmar la recepción del turno.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    /** @return list<array{id: int, username: string}> */
    private function recipientCandidates(int $actorId): array
    {
        $recipients = [];
        foreach ((new UserDirectoryService())->all() as $user) {
            $userId = (int) $user['id'];
            if ($userId !== $actorId && $user['active'] && Access::can($userId, 'caja.entregar')) {
                $recipients[] = ['id' => $userId, 'username' => (string) $user['username']];
            }
        }

        return $recipients;
    }

    private function signedDecimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . Money::decimal(abs($cents));
    }

    private function signedCents(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $cents = Money::cents($negative ? substr($value, 1) : $value, true);

        return $negative ? -$cents : $cents;
    }

    private function localDateTime(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
    }
}
