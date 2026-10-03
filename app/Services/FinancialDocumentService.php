<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\InstallmentSchedule;
use App\Domain\Money;
use App\Domain\ValidationException;

final class FinancialDocumentService
{
    /** @return list<array<string, mixed>> */
    public function listForClient(int $clientId): array
    {
        $rows = db_connect()->query("SELECT o.id, o.concepto, o.fecha_origen, o.importe_base,
            o.operacion_confirmacion_id, d.tipo, d.numero_completo
            FROM obligaciones o
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            WHERE o.cliente_id = ?
            ORDER BY o.fecha_origen DESC, o.id DESC", [$clientId])->getResultArray();

        foreach ($rows as &$row) {
            $row['estado'] = $row['operacion_confirmacion_id'] === null ? 'BORRADOR' : 'CONFIRMADO';
            $row['saldo'] = $this->balance((int) $row['id']);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function find(int $obligationId): array
    {
        $db = db_connect();
        $row = $db->query("SELECT o.id, o.cliente_id, o.concepto, o.fecha_origen, o.importe_base,
            o.operacion_confirmacion_id, d.tipo, d.numero_completo, d.fecha_emision,
            c.nombre cliente, c.activo cliente_activo
            FROM obligaciones o
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            JOIN clientes c ON c.id = o.cliente_id
            WHERE o.id = ?", [$obligationId])->getRowArray();
        if (!$row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $row['estado'] = $row['operacion_confirmacion_id'] === null ? 'BORRADOR' : 'CONFIRMADO';
        $row['saldo'] = $this->balance($obligationId);
        $row['detalles'] = $db->table('obligacion_detalles')
            ->where('obligacion_id', $obligationId)->orderBy('renglon')->get()->getResultArray();
        $row['draft_revision'] = hash('sha256', json_encode([
            'id' => $row['id'], 'cliente_id' => $row['cliente_id'], 'concepto' => $row['concepto'],
            'fecha_origen' => $row['fecha_origen'], 'importe_base' => $row['importe_base'],
            'tipo' => $row['tipo'], 'numero_completo' => $row['numero_completo'],
            'fecha_emision' => $row['fecha_emision'], 'detalles' => $row['detalles'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $row['cuotas'] = $db->query("SELECT c.id, c.numero_cuota, cv.fecha_vencimiento, cv.importe_programado
            FROM cuotas c
            JOIN cuota_versiones cv ON cv.cuota_id = c.id
             AND cv.version = (SELECT MAX(cv2.version) FROM cuota_versiones cv2 WHERE cv2.cuota_id = c.id)
            WHERE c.obligacion_id = ? ORDER BY c.numero_cuota", [$obligationId])->getResultArray();

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function portfolioForClient(int $clientId): array
    {
        $rows = db_connect()->query("SELECT o.id, o.concepto, o.importe_base,
            MIN(CASE WHEN cv.importe_programado > COALESCE((
                SELECT SUM(ap.importe)
                FROM aplicaciones_pago ap
                LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
                LEFT JOIN pago_reversiones pr ON pr.pago_id = ap.pago_id
                WHERE ap.cuota_id = c.id AND ar.aplicacion_id IS NULL AND pr.pago_id IS NULL
            ), 0) THEN cv.fecha_vencimiento END) vencimiento
            FROM obligaciones o
            JOIN cuotas c ON c.obligacion_id = o.id
            JOIN cuota_versiones cv ON cv.cuota_id = c.id
             AND cv.version = (SELECT MAX(cv2.version) FROM cuota_versiones cv2 WHERE cv2.cuota_id = c.id)
            WHERE o.cliente_id = ? AND o.operacion_confirmacion_id IS NOT NULL
            GROUP BY o.id, o.concepto, o.importe_base
            ORDER BY vencimiento, o.id", [$clientId])->getResultArray();
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        foreach ($rows as &$row) {
            $row['saldo'] = $this->balance((int) $row['id']);
            $row['estado'] = $row['saldo'] === '0.00' ? 'RECUPERADA' : (($row['vencimiento'] ?? '') < $today ? 'VENCIDA' : 'CONFIRMADO');
        }

        return $rows;
    }

    public function createDraft(int $actorId, int $clientId, array $input, string $idempotencyKey): array
    {
        Access::require($actorId, 'cartera.ver');
        $data = $this->validateDraft($input);
        $payload = ['client_id' => $clientId] + $data;
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'documentos.borrador.crear', $idempotencyKey, $payload);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            $client = $db->query('SELECT id, activo FROM clientes WHERE id = ? FOR UPDATE', [$clientId])->getRowArray();
            if (!$client) {
                throw new ValidationException(['client' => 'El cliente no existe.']);
            }
            if ((int) $client['activo'] !== 1) {
                throw new ValidationException(['client' => 'El cliente inactivo no admite documentos nuevos.']);
            }

            $db->table('obligaciones')->insert([
                'cliente_id' => $clientId,
                'origen' => 'VENTA',
                'concepto' => $data['concept'],
                'fecha_origen' => $data['issued_on'],
                'importe_base' => $data['amount'],
                'operacion_creacion_id' => $operation['id'],
            ]);
            $obligationId = (int) $db->insertID();
            $db->table('documentos_obligacion')->insert([
                'obligacion_id' => $obligationId,
                'tipo' => $data['type'],
                'numero_completo' => $data['number'] === '' ? null : $data['number'],
                'numero_normalizado' => $data['normalized_number'] === '' ? null : $data['normalized_number'],
                'fecha_emision' => $data['issued_on'],
            ]);
            foreach ($data['lines'] as $index => $line) {
                $db->table('obligacion_detalles')->insert([
                    'obligacion_id' => $obligationId,
                    'renglon' => $index + 1,
                    'item_id' => $line['item_id'],
                    'descripcion_pactada' => $line['description'],
                    'cantidad' => $line['quantity'],
                    'importe_linea_documentado' => $line['amount'],
                ]);
            }
            $result = ['obligation_id' => $obligationId, 'amount' => $data['amount'], 'status' => 'BORRADOR'];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'documentos.borrador.crear', 'obligacion:' . $obligationId, ['importe' => $data['amount']]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo guardar el borrador.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            if ((int) $error->getCode() === 1062) {
                throw new ValidationException(['number' => 'Ya existe un documento con ese tipo y número.']);
            }
            throw $error;
        }
    }

    public function updateDraft(int $actorId, int $obligationId, array $input, string $idempotencyKey, string $expectedRevision): array
    {
        Access::require($actorId, 'cartera.ver');
        $data = $this->validateDraft($input);
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'documentos.borrador.editar', $idempotencyKey, [
                'obligation_id' => $obligationId, 'revision' => $expectedRevision, 'document' => $data,
            ]);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            // Confirmation takes the same row lock: an edit cannot cross its state transition.
            $obligation = $db->query('SELECT * FROM obligaciones WHERE id = ? FOR UPDATE', [$obligationId])->getRowArray();
            if (!$obligation) {
                throw new ValidationException(['document' => 'El documento no existe.']);
            }
            if ($obligation['operacion_confirmacion_id'] !== null) {
                throw new ValidationException(['document' => 'El documento confirmado no admite edición.']);
            }
            $before = $this->find($obligationId);
            if (!hash_equals($before['draft_revision'], $expectedRevision)) {
                throw new ValidationException(['draft_revision' => 'El borrador cambió desde que abriste el formulario. Revisa la versión guardada y vuelve a editar.']);
            }
            $db->table('obligaciones')->where('id', $obligationId)->update([
                'concepto' => $data['concept'], 'fecha_origen' => $data['issued_on'], 'importe_base' => $data['amount'],
            ]);
            $db->table('documentos_obligacion')->where('obligacion_id', $obligationId)->update([
                'tipo' => $data['type'],
                'numero_completo' => $data['number'] === '' ? null : $data['number'],
                'numero_normalizado' => $data['normalized_number'] === '' ? null : $data['normalized_number'],
                'fecha_emision' => $data['issued_on'],
            ]);
            $db->table('obligacion_detalles')->where('obligacion_id', $obligationId)->delete();
            foreach ($data['lines'] as $index => $line) {
                $db->table('obligacion_detalles')->insert([
                    'obligacion_id' => $obligationId, 'renglon' => $index + 1,
                    'item_id' => $line['item_id'], 'descripcion_pactada' => $line['description'],
                    'cantidad' => $line['quantity'], 'importe_linea_documentado' => $line['amount'],
                ]);
            }
            $result = ['obligation_id' => $obligationId, 'amount' => $data['amount'], 'status' => 'BORRADOR'];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'documentos.borrador.editar', 'obligacion:' . $obligationId, [
                'importe_anterior' => $before['importe_base'], 'importe' => $data['amount'],
                'lineas_anteriores' => count($before['detalles']), 'lineas' => count($data['lines']),
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo actualizar el borrador.');
            }
            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            if ((int) $error->getCode() === 1062) {
                throw new ValidationException(['number' => 'Ya existe un documento con ese tipo y número.']);
            }
            throw $error;
        }
    }

    public function confirmMonthly(int $actorId, int $obligationId, mixed $months, mixed $firstDueOn, string $idempotencyKey): array
    {
        Access::require($actorId, 'cartera.ver');
        $document = $this->find($obligationId);
        $schedule = InstallmentSchedule::monthly((string) $document['importe_base'], $months, $firstDueOn, (string) $document['fecha_emision']);

        return $this->confirm($actorId, $obligationId, array_column($schedule, 'due_date'), $idempotencyKey);
    }

    public function confirm(int $actorId, int $obligationId, array $dueDates, string $idempotencyKey): array
    {
        Access::require($actorId, 'cartera.ver');
        $db = db_connect();
        $db->resetTransStatus()->transException(true)->transBegin();

        try {
            $operation = (new OperationService())->reserve($db, $actorId, 'documentos.confirmar', $idempotencyKey, [
                'obligation_id' => $obligationId,
                'due_dates' => array_values($dueDates),
            ]);
            if ($operation['result'] !== null) {
                $db->transRollback();
                return $operation['result'];
            }
            $obligation = $db->query('SELECT o.* FROM obligaciones o JOIN clientes c ON c.id = o.cliente_id WHERE o.id = ? FOR UPDATE', [$obligationId])->getRowArray();
            if (!$obligation) {
                throw new ValidationException(['document' => 'El documento no existe.']);
            }
            if ($obligation['operacion_confirmacion_id'] !== null) {
                throw new ValidationException(['document' => 'El documento ya fue confirmado.']);
            }
            $schedule = InstallmentSchedule::equal((string) $obligation['importe_base'], $dueDates);
            $installments = [];
            foreach ($schedule as $row) {
                $db->table('cuotas')->insert(['obligacion_id' => $obligationId, 'numero_cuota' => $row['number']]);
                $installmentId = (int) $db->insertID();
                $db->table('cuota_versiones')->insert([
                    'cuota_id' => $installmentId,
                    'version' => 1,
                    'fecha_vencimiento' => $row['due_date'],
                    'importe_programado' => $row['amount'],
                    'operacion_id' => $operation['id'],
                ]);
                $installments[] = ['id' => $installmentId] + $row;
            }
            $db->table('obligaciones')->where('id', $obligationId)->update(['operacion_confirmacion_id' => $operation['id']]);
            $result = ['obligation_id' => $obligationId, 'amount' => (string) $obligation['importe_base'], 'status' => 'CONFIRMADO', 'installments' => $installments];
            (new OperationService())->complete($db, (int) $operation['id'], $result);
            Audit::record($actorId, 'documentos.confirmar', 'obligacion:' . $obligationId, ['cuotas' => count($installments)]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('No se pudo confirmar el documento.');
            }

            return $result;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public function balance(int $obligationId): string
    {
        $db = db_connect();
        $obligation = $db->table('obligaciones')->select('operacion_confirmacion_id')->where('id', $obligationId)->get()->getRowArray();
        if (!$obligation || $obligation['operacion_confirmacion_id'] === null) {
            return '0.00';
        }
        $rows = $db->query("SELECT cv.importe_programado,
            COALESCE(SUM(CASE WHEN ar.aplicacion_id IS NULL AND pr.pago_id IS NULL THEN ap.importe ELSE 0 END), 0) aplicado
            FROM cuotas c
            JOIN cuota_versiones cv ON cv.cuota_id = c.id
              AND cv.version = (SELECT MAX(cv2.version) FROM cuota_versiones cv2 WHERE cv2.cuota_id = c.id)
            LEFT JOIN aplicaciones_pago ap ON ap.cuota_id = c.id
            LEFT JOIN aplicacion_reversiones ar ON ar.aplicacion_id = ap.id
            LEFT JOIN pagos p ON p.id = ap.pago_id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = p.id
            WHERE c.obligacion_id = ?
            GROUP BY c.id, cv.importe_programado", [$obligationId])->getResultArray();
        $cents = 0;
        foreach ($rows as $row) {
            $cents += Money::cents((string) $row['importe_programado']) - Money::cents((string) $row['aplicado'], true);
        }

        return Money::decimal($cents);
    }

    private function validateDraft(array $input): array
    {
        $errors = [];
        $type = strtoupper(trim(is_string($input['type'] ?? null) ? $input['type'] : ''));
        $number = strtoupper(trim(is_string($input['number'] ?? null) ? $input['number'] : ''));
        $normalizedNumber = preg_replace('/[^A-Z0-9]+/', '', $number) ?? '';
        $issuedOn = is_string($input['issued_on'] ?? null) ? $input['issued_on'] : '';
        $concept = trim(is_string($input['concept'] ?? null) ? $input['concept'] : '');
        if (!in_array($type, ['FACTURA', 'NOTA_VENTA', 'OTRO'], true)) {
            $errors['type'] = 'Selecciona un tipo documental válido.';
        }
        if ($number !== '' && ($normalizedNumber === '' || mb_strlen($number) > 100)) {
            $errors['number'] = 'El número documental no es válido.';
        }
        if (!$this->isDate($issuedOn)) {
            $errors['issued_on'] = 'La fecha de emisión no es válida.';
        }
        if (mb_strlen($concept) < 2 || mb_strlen($concept) > 300) {
            $errors['concept'] = 'Escribe un concepto de 2 a 300 caracteres.';
        }
        $sourceLines = is_array($input['lines'] ?? null) ? array_values($input['lines']) : [];
        if ($sourceLines === []) {
            $errors['lines'] = 'Añade al menos una línea.';
        }
        $lines = [];
        $totalCents = 0;
        foreach ($sourceLines as $index => $line) {
            if (!is_array($line)) {
                $errors['lines'] = 'Las líneas del documento no son válidas.';
                continue;
            }
            $description = trim(is_string($line['description'] ?? null) ? $line['description'] : '');
            $quantity = trim(is_string($line['quantity'] ?? null) ? $line['quantity'] : '');
            try {
                $amountCents = Money::cents(is_string($line['amount'] ?? null) ? $line['amount'] : '');
            } catch (\InvalidArgumentException $error) {
                $errors['lines.' . $index . '.amount'] = $error->getMessage();
                $amountCents = 0;
            }
            if (mb_strlen($description) < 2 || mb_strlen($description) > 300) {
                $errors['lines.' . $index . '.description'] = 'Escribe una descripción de 2 a 300 caracteres.';
            }
            if (!preg_match('/^(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,4})?$/D', $quantity) || (float) $quantity <= 0) {
                $errors['lines.' . $index . '.quantity'] = 'La cantidad debe ser mayor que cero y tener hasta cuatro decimales.';
            }
            $itemId = $line['item_id'] ?? null;
            if ($itemId !== null && filter_var($itemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $errors['lines.' . $index . '.item_id'] = 'El ítem seleccionado no es válido.';
            }
            $totalCents += $amountCents;
            $lines[] = ['item_id' => $itemId === null ? null : (int) $itemId, 'description' => $description, 'quantity' => $quantity, 'amount' => Money::decimal($amountCents)];
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        return ['type' => $type, 'number' => $number, 'normalized_number' => $normalizedNumber, 'issued_on' => $issuedOn, 'concept' => $concept, 'amount' => Money::decimal($totalCents), 'lines' => $lines];
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
