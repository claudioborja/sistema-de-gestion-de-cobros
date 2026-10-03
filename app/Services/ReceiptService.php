<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use Dompdf\Dompdf;
use Dompdf\Options;

final class ReceiptService
{
    /** @return array{id: int, code: string} */
    public function issueForPayment(BaseConnection $db, int $paymentId, int $actorId): array
    {
        $existing = $db->query("SELECT r.id, r.serie, r.numero
            FROM recibo_pagos rp JOIN recibos r ON r.id = rp.recibo_id
            WHERE rp.pago_id = ?", [$paymentId])->getRowArray();
        if ($existing) {
            return ['id' => (int) $existing['id'], 'code' => $this->code($existing['serie'], (int) $existing['numero'])];
        }

        $payment = $db->query("SELECT p.id, p.cliente_id, p.importe, p.fecha_declarada, p.medio_pago,
            p.operacion_confirmacion_id, c.nombre cliente, pb.referencia, pb.fecha_bancaria,
            cb.institucion, cb.alias, op.efectiva_en, u.username responsable
            FROM pagos p
            JOIN clientes c ON c.id = p.cliente_id
            JOIN operaciones op ON op.id = p.operacion_confirmacion_id
            JOIN users u ON u.id = op.usuario_id
            LEFT JOIN pagos_bancarios pb ON pb.pago_id = p.id
            LEFT JOIN cuentas_bancarias cb ON cb.id = pb.cuenta_bancaria_id
            WHERE p.id = ? FOR UPDATE", [$paymentId])->getRowArray();
        if (!$payment) {
            throw PageNotFoundException::forPageNotFound();
        }

        $identification = $db->query("SELECT tipo, numero_normalizado
            FROM cliente_identificaciones WHERE cliente_id = ?
            ORDER BY pais_emisor, tipo, numero_normalizado LIMIT 1", [$payment['cliente_id']])->getRowArray();
        $applications = $db->query("SELECT ap.importe, o.id obligacion_id, o.concepto,
            d.tipo, d.numero_completo, c.numero_cuota
            FROM aplicaciones_pago ap
            JOIN cuotas c ON c.id = ap.cuota_id
            JOIN obligaciones o ON o.id = c.obligacion_id
            JOIN documentos_obligacion d ON d.obligacion_id = o.id
            WHERE ap.pago_id = ? ORDER BY ap.id", [$paymentId])->getResultArray();

        $business = $this->business($db);
        $series = (string) $business['invoice_prefix'];
        $seed = max(1, (int) $business['invoice_seed']);
        $db->query('INSERT IGNORE INTO recibo_series (serie, siguiente_numero) VALUES (?, ?)', [$series, $seed]);
        $sequence = $db->query('SELECT siguiente_numero FROM recibo_series WHERE serie = ? FOR UPDATE', [$series])->getRowArray();
        $number = (int) $sequence['siguiente_numero'];
        $db->table('recibo_series')->where('serie', $series)->update(['siguiente_numero' => $number + 1]);

        $appliedCents = 0;
        $applicationSnapshot = [];
        foreach ($applications as $application) {
            $appliedCents += Money::cents((string) $application['importe']);
            $applicationSnapshot[] = [
                'obligation_id' => (int) $application['obligacion_id'],
                'document' => $application['numero_completo'] ?: $application['tipo'],
                'installment_number' => (int) $application['numero_cuota'],
                'concept' => (string) $application['concepto'],
                'amount' => Money::decimal(Money::cents((string) $application['importe'])),
            ];
        }
        $totalCents = Money::cents((string) $payment['importe']);
        $snapshot = [
            'business' => [
                'name' => (string) $business['business_name'],
                'identification' => $business['document_type'] === 'No aplica' || $business['document_value'] === ''
                    ? null
                    : trim($business['document_type'] . ' ' . $business['document_value']),
                'currency' => (string) $business['currency'],
                'footer' => (string) $business['invoice_footer'],
            ],
            'client' => [
                'id' => (int) $payment['cliente_id'],
                'name' => (string) $payment['cliente'],
                'identification' => $identification
                    ? trim($identification['tipo'] . ' ' . $identification['numero_normalizado'])
                    : null,
            ],
            'responsible' => (string) $payment['responsable'],
            'issued_at' => (string) $payment['efectiva_en'],
            'total' => Money::decimal($totalCents),
            'applied' => Money::decimal($appliedCents),
            'available' => Money::decimal($totalCents - $appliedCents),
            'payments' => [[
                'id' => (int) $payment['id'],
                'method' => (string) $payment['medio_pago'],
                'amount' => Money::decimal($totalCents),
                'declared_on' => (string) ($payment['fecha_declarada'] ?? ''),
                'bank_date' => (string) ($payment['fecha_bancaria'] ?? ''),
                'reference' => (string) ($payment['referencia'] ?? ''),
                'institution' => (string) ($payment['institucion'] ?? ''),
                'account_alias' => (string) ($payment['alias'] ?? ''),
            ]],
            'applications' => $applicationSnapshot,
        ];

        $db->table('recibos')->insert([
            'serie' => $series,
            'numero' => $number,
            'operacion_emision_id' => (int) $payment['operacion_confirmacion_id'],
            'cliente_id' => (int) $payment['cliente_id'],
            'contenido_json' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'emitido_por' => $actorId,
            'emitido_en' => $payment['efectiva_en'],
        ]);
        $receiptId = (int) $db->insertID();
        $db->table('recibo_pagos')->insert(['recibo_id' => $receiptId, 'pago_id' => $paymentId]);
        Audit::record($actorId, 'recibos.emitir', 'recibo:' . $receiptId, [
            'numero' => $this->code($series, $number),
            'pago_id' => $paymentId,
        ]);

        return ['id' => $receiptId, 'code' => $this->code($series, $number)];
    }

    /** @return array<string, mixed> */
    public function find(int $actorId, int $receiptId): array
    {
        Access::require($actorId, 'pagos.ver');
        $row = db_connect()->query("SELECT r.id, r.serie, r.numero, r.contenido_json, r.emitido_en,
            MAX(CASE WHEN pr.pago_id IS NOT NULL THEN 1 ELSE 0 END) revertido,
            MAX(ro.motivo) motivo_reversion, MAX(ro.efectiva_en) fecha_reversion
            FROM recibos r
            JOIN recibo_pagos rp ON rp.recibo_id = r.id
            LEFT JOIN pago_reversiones pr ON pr.pago_id = rp.pago_id
            LEFT JOIN operaciones ro ON ro.id = pr.operacion_id
            WHERE r.id = ?
            GROUP BY r.id, r.serie, r.numero, r.contenido_json, r.emitido_en", [$receiptId])->getRowArray();
        if (!$row) {
            throw PageNotFoundException::forPageNotFound();
        }

        return [
            'id' => (int) $row['id'],
            'code' => $this->code($row['serie'], (int) $row['numero']),
            'issued_at' => (string) $row['emitido_en'],
            'snapshot' => json_decode($row['contenido_json'], true, 512, JSON_THROW_ON_ERROR),
            'payment_status' => (int) $row['revertido'] === 1 ? 'REVERTIDO' : 'VIGENTE',
            'reversal_reason' => $row['motivo_reversion'],
            'reversed_at' => $row['fecha_reversion'],
        ];
    }

    /** @return array<string, mixed>|null */
    public function findForPayment(int $actorId, int $paymentId): ?array
    {
        Access::require($actorId, 'pagos.ver');
        $row = db_connect()->table('recibo_pagos')->select('recibo_id')->where('pago_id', $paymentId)->get()->getRowArray();

        return $row ? $this->find($actorId, (int) $row['recibo_id']) : null;
    }

    public function renderPdf(int $actorId, int $receiptId): string
    {
        $receipt = $this->find($actorId, $receiptId);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('receipts/pdf', ['receipt' => $receipt]), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @return array<string, mixed> */
    private function business(BaseConnection $db): array
    {
        $defaults = [
            'business_name' => 'Mi negocio', 'document_type' => 'RUC', 'document_value' => '',
            'currency' => 'USD', 'invoice_prefix' => 'FAC', 'invoice_seed' => 1,
            'invoice_footer' => 'Gracias por su pago.',
        ];
        $row = $db->table('configuracion_sistema')->where('clave', 'configuration_business')->get()->getRowArray();
        $values = $row ? json_decode($row['valor'], true, 512, JSON_THROW_ON_ERROR) : [];
        $business = array_replace($defaults, is_array($values) ? $values : []);
        $series = mb_strtoupper(trim((string) $business['invoice_prefix']));
        $business['invoice_prefix'] = preg_match('/^[A-Z0-9._-]{1,15}$/D', $series) ? $series : 'FAC';

        return $business;
    }

    private function code(string $series, int $number): string
    {
        return $series . '-' . str_pad((string) $number, 8, '0', STR_PAD_LEFT);
    }
}
