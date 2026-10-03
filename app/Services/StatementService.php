<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money;

final class StatementService
{
    private const TYPES = ['' => 'Todos', 'documentos' => 'Documentos', 'pagos' => 'Cobros', 'correcciones' => 'Correcciones'];

    /** @return array<string, mixed> */
    public function forClient(int $clientId, array $input): array
    {
        $from = $this->validDate($input['desde'] ?? null);
        $to = $this->validDate($input['hasta'] ?? null);
        $type = is_string($input['tipo'] ?? null) && isset(self::TYPES[$input['tipo']]) ? $input['tipo'] : '';
        $errors = [];
        if (is_string($input['desde'] ?? null) && $input['desde'] !== '' && $from === '') {
            $errors[] = 'La fecha inicial no es válida.';
        }
        if (is_string($input['hasta'] ?? null) && $input['hasta'] !== '' && $to === '') {
            $errors[] = 'La fecha final no es válida.';
        }
        if ($from !== '' && $to !== '' && $from > $to) {
            $errors[] = 'La fecha inicial no puede ser posterior a la fecha final.';
        }

        $events = $this->events($clientId);
        usort($events, static fn (array $left, array $right): int => [
            $left['date'], $left['priority'], $left['id'],
        ] <=> [
            $right['date'], $right['priority'], $right['id'],
        ]);

        $runningCents = 0;
        $openingCents = 0;
        $periodDebits = 0;
        $periodCredits = 0;
        $visible = [];
        if ($errors === []) {
            foreach ($events as $event) {
                if ($to !== '' && $event['date'] > $to) {
                    continue;
                }
                $runningCents += $event['debit_cents'] - $event['credit_cents'];
                $event['balance'] = $this->currency($runningCents);
                $event['debit'] = $event['debit_cents'] > 0 ? $this->currency($event['debit_cents']) : '—';
                $event['credit'] = $event['credit_cents'] > 0 ? $this->currency($event['credit_cents']) : '—';
                if ($from !== '' && $event['date'] < $from) {
                    $openingCents = $runningCents;
                    continue;
                }
                $periodDebits += $event['debit_cents'];
                $periodCredits += $event['credit_cents'];
                if ($type === '' || $event['category'] === $type) {
                    $visible[] = $event;
                }
            }
        }

        $total = count($visible);
        $visible = array_reverse($visible);
        $rows = $visible;

        $portfolioCents = 0;
        foreach ((new FinancialDocumentService())->portfolioForClient($clientId) as $obligation) {
            $portfolioCents += Money::cents((string) $obligation['saldo'], true);
        }
        $availableCents = 0;
        foreach ((new PaymentService())->listForClient($clientId) as $payment) {
            $availableCents += Money::cents((string) $payment['disponible'], true);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'filters' => ['desde' => $from, 'hasta' => $to, 'tipo' => $type],
            'filterErrors' => $errors,
            'typeOptions' => self::TYPES,
            'openingBalance' => $this->currency($openingCents),
            'closingBalance' => $this->currency($runningCents),
            'periodDebits' => $this->currency($periodDebits),
            'periodCredits' => $this->currency($periodCredits),
            'portfolioBalance' => $this->currency($portfolioCents),
            'availableBalance' => $this->currency($availableCents),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function events(int $clientId): array
    {
        $db = db_connect();
        $events = [];
        $documents = $db->query("SELECT o.id, o.fecha_origen fecha, o.importe_base importe, o.concepto,
            d.tipo, d.numero_completo
            FROM obligaciones o
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            WHERE o.cliente_id = ? AND o.operacion_confirmacion_id IS NOT NULL", [$clientId])->getResultArray();
        foreach ($documents as $document) {
            $events[] = [
                'id' => (int) $document['id'],
                'date' => (string) $document['fecha'],
                'priority' => 10,
                'category' => 'documentos',
                'kind' => 'DOCUMENTO',
                'reference' => trim((string) $document['tipo'] . ' ' . ($document['numero_completo'] ?: '#' . $document['id'])),
                'description' => (string) $document['concepto'],
                'url' => site_url('obligaciones/' . (int) $document['id']),
                'debit_cents' => Money::cents((string) $document['importe']),
                'credit_cents' => 0,
            ];
        }

        $payments = $db->query("SELECT p.id, COALESCE(p.fecha_declarada, DATE(op.efectiva_en)) fecha,
            p.importe, p.medio_pago, pb.referencia
            FROM pagos p
            JOIN operaciones op ON op.id = p.operacion_confirmacion_id
            LEFT JOIN pagos_bancarios pb ON pb.pago_id = p.id
            WHERE p.cliente_id = ?", [$clientId])->getResultArray();
        foreach ($payments as $payment) {
            $events[] = [
                'id' => (int) $payment['id'],
                'date' => (string) $payment['fecha'],
                'priority' => 20,
                'category' => 'pagos',
                'kind' => 'COBRO',
                'reference' => $payment['referencia'] ?: 'Pago #' . $payment['id'],
                'description' => 'Cobro recibido por ' . mb_strtolower((string) ($payment['medio_pago'] ?: 'medio no indicado')) . '.',
                'url' => site_url('pagos/' . (int) $payment['id']),
                'debit_cents' => 0,
                'credit_cents' => Money::cents((string) $payment['importe']),
            ];
        }

        $reversals = $db->query("SELECT p.id, DATE(op.efectiva_en) fecha, p.importe, op.motivo
            FROM pago_reversiones pr
            JOIN pagos p ON p.id = pr.pago_id
            JOIN operaciones op ON op.id = pr.operacion_id
            WHERE p.cliente_id = ?", [$clientId])->getResultArray();
        foreach ($reversals as $reversal) {
            $events[] = [
                'id' => (int) $reversal['id'],
                'date' => (string) $reversal['fecha'],
                'priority' => 30,
                'category' => 'correcciones',
                'kind' => 'REVERSIÓN',
                'reference' => 'Reversión pago #' . $reversal['id'],
                'description' => (string) ($reversal['motivo'] ?: 'Corrección administrativa del cobro.'),
                'url' => site_url('pagos/' . (int) $reversal['id']),
                'debit_cents' => Money::cents((string) $reversal['importe']),
                'credit_cents' => 0,
            ];
        }

        return $events;
    }

    private function validDate(mixed $value): string
    {
        if (!is_string($value) || $value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function currency(int $cents): string
    {
        return ($cents < 0 ? '-$' : '$') . Money::decimal(abs($cents));
    }
}
