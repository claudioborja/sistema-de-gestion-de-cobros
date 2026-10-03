<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FinancialDocumentService;
use App\Services\PaymentService;
use App\Services\ReceiptService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ReceiptTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $refresh = true;

    /** @return array{actor: User, client_id: int, installment_id: int, account_id: int} */
    private function fixture(): array
    {
        $users = model(UserModel::class);
        $suffix = bin2hex(random_bytes(4));
        $actorId = (int) $users->insert(new User([
            'username' => 'receipt-' . $suffix,
            'email' => 'receipt-' . $suffix . '@example.test',
            'password' => 'Fixture-only-' . bin2hex(random_bytes(16)),
            'active' => 1,
        ]));
        $actor = $users->findById($actorId);
        $actor->syncGroups('administrador');

        $this->db->table('clientes')->insert([
            'nombre' => 'Cliente original', 'activo' => 1, 'version' => 1,
            'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);
        $clientId = (int) $this->db->insertID();
        $this->db->table('cliente_identificaciones')->insert([
            'pais_emisor' => 'EC', 'tipo' => 'RUC',
            'numero_normalizado' => '1790012345001', 'cliente_id' => $clientId,
        ]);
        $this->db->table('configuracion_sistema')->upsert([
            'clave' => 'configuration_business',
            'valor' => json_encode([
                'business_name' => 'Negocio original', 'document_type' => 'RUC',
                'document_value' => '1799999999001', 'currency' => 'USD',
                'timezone' => 'America/Guayaquil', 'invoice_prefix' => 'REC',
                'invoice_seed' => 41, 'invoice_footer' => 'Gracias por su pago.',
            ], JSON_THROW_ON_ERROR),
        ]);

        $documents = new FinancialDocumentService();
        $draft = $documents->createDraft($actorId, $clientId, [
            'type' => 'FACTURA', 'number' => 'FAC-' . $suffix,
            'issued_on' => '2026-09-18', 'concept' => 'Servicio documentado',
            'lines' => [['description' => 'Servicio', 'quantity' => '1', 'amount' => '100.00']],
        ], 'receipt-draft-' . $suffix);
        $confirmed = $documents->confirm(
            $actorId,
            (int) $draft['obligation_id'],
            ['2026-10-18'],
            'receipt-confirm-' . $suffix
        );
        $this->db->table('cuentas_bancarias')->insert([
            'institucion' => 'Banco de prueba', 'numero_cuenta' => 'QA-' . $suffix,
            'alias' => 'Cuenta principal', 'activa' => 1,
        ]);

        return [
            'actor' => $actor,
            'client_id' => $clientId,
            'installment_id' => (int) $confirmed['installments'][0]['id'],
            'account_id' => (int) $this->db->insertID(),
        ];
    }

    /** @return array<string, mixed> */
    private function paymentInput(array $fixture): array
    {
        return [
            'amount' => '40.00', 'bank_account_id' => $fixture['account_id'],
            'reference' => 'TRX-RECIBO-001', 'bank_date' => '2026-09-18',
            'applications' => [[
                'installment_id' => $fixture['installment_id'], 'amount' => '30.00',
            ]],
        ];
    }

    public function testPaymentConfirmationEmitsOneImmutableReceiptSnapshot(): void
    {
        $fixture = $this->fixture();
        $result = (new PaymentService())->confirmBankTransfer(
            (int) $fixture['actor']->id,
            $fixture['client_id'],
            $this->paymentInput($fixture),
            'receipt-payment-001'
        );

        $this->assertArrayHasKey('receipt_id', $result);
        $receipt = (new ReceiptService())->find((int) $fixture['actor']->id, (int) $result['receipt_id']);
        $this->assertSame('REC-00000041', $receipt['code']);
        $this->assertSame('Cliente original', $receipt['snapshot']['client']['name']);
        $this->assertSame('RUC 1790012345001', $receipt['snapshot']['client']['identification']);
        $this->assertSame('Negocio original', $receipt['snapshot']['business']['name']);
        $this->assertSame('40.00', $receipt['snapshot']['total']);
        $this->assertSame('30.00', $receipt['snapshot']['applications'][0]['amount']);
        $this->assertSame('10.00', $receipt['snapshot']['available']);
        $this->assertSame('VIGENTE', $receipt['payment_status']);
        $this->assertSame(1, $this->db->table('recibos')->countAllResults());
        $this->assertSame(1, $this->db->table('recibo_pagos')->countAllResults());
    }

    public function testIdempotentPaymentReturnsOriginalReceiptWithoutConsumingAnotherNumber(): void
    {
        $fixture = $this->fixture();
        $payments = new PaymentService();
        $first = $payments->confirmBankTransfer(
            (int) $fixture['actor']->id,
            $fixture['client_id'],
            $this->paymentInput($fixture),
            'receipt-payment-replay'
        );
        $second = $payments->confirmBankTransfer(
            (int) $fixture['actor']->id,
            $fixture['client_id'],
            $this->paymentInput($fixture),
            'receipt-payment-replay'
        );

        $this->assertSame($first['receipt_id'], $second['receipt_id']);
        $this->assertSame(1, $this->db->table('recibos')->countAllResults());
        $sequence = $this->db->table('recibo_series')->where('serie', 'REC')->get()->getRowArray();
        $this->assertSame(42, (int) $sequence['siguiente_numero']);
    }

    public function testReprintKeepsSnapshotAndReportsLaterPaymentReversal(): void
    {
        $fixture = $this->fixture();
        $payments = new PaymentService();
        $result = $payments->confirmBankTransfer(
            (int) $fixture['actor']->id,
            $fixture['client_id'],
            $this->paymentInput($fixture),
            'receipt-payment-reversal'
        );
        $this->db->table('clientes')->where('id', $fixture['client_id'])->update(['nombre' => 'Cliente cambiado']);
        $this->db->table('configuracion_sistema')->where('clave', 'configuration_business')->update([
            'valor' => json_encode(['business_name' => 'Negocio cambiado'], JSON_THROW_ON_ERROR),
        ]);
        $payments->reversePayment(
            (int) $fixture['actor']->id,
            (int) $result['payment_id'],
            'Transferencia registrada por duplicado.',
            'receipt-payment-reverse'
        );

        $receipt = (new ReceiptService())->find((int) $fixture['actor']->id, (int) $result['receipt_id']);
        $this->assertSame('Cliente original', $receipt['snapshot']['client']['name']);
        $this->assertSame('Negocio original', $receipt['snapshot']['business']['name']);
        $this->assertSame('REVERTIDO', $receipt['payment_status']);
        $this->assertSame('Transferencia registrada por duplicado.', $receipt['reversal_reason']);
    }

    public function testPdfCopyUsesReceiptNumberSnapshotAndReversalState(): void
    {
        $fixture = $this->fixture();
        $payments = new PaymentService();
        $result = $payments->confirmBankTransfer(
            (int) $fixture['actor']->id,
            $fixture['client_id'],
            $this->paymentInput($fixture),
            'receipt-payment-pdf'
        );
        $payments->reversePayment(
            (int) $fixture['actor']->id,
            (int) $result['payment_id'],
            'Transferencia duplicada para prueba de copia.',
            'receipt-payment-pdf-reverse'
        );

        $pdf = (new ReceiptService())->renderPdf((int) $fixture['actor']->id, (int) $result['receipt_id']);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1500, strlen($pdf));
    }

    public function testPortalClientCannotReadOrGenerateReceipt(): void
    {
        $fixture = $this->fixture();
        $result = (new PaymentService())->confirmBankTransfer(
            (int) $fixture['actor']->id,
            $fixture['client_id'],
            $this->paymentInput($fixture),
            'receipt-payment-denied'
        );
        $clientActorId = (int) model(UserModel::class)->insert(new User([
            'username' => 'receipt-client', 'email' => 'receipt-client@example.test',
            'password' => 'Fixture-client-only', 'active' => 1,
        ]));
        model(UserModel::class)->findById($clientActorId)->syncGroups('cliente');
        $service = new ReceiptService();

        foreach (['find', 'renderPdf'] as $method) {
            $denied = false;
            try {
                $service->{$method}($clientActorId, (int) $result['receipt_id']);
            } catch (\RuntimeException $error) {
                $this->assertSame(403, $error->getCode());
                $denied = true;
            }
            $this->assertTrue($denied);
        }
    }
}
