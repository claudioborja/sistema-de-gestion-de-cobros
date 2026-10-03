<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FinancialDocumentService;
use App\Services\PaymentService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ReceiptFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    protected $refresh = true;

    /** @return array{admin: User, client: User, receipt_id: int, payment_id: int, code: string} */
    private function fixture(): array
    {
        $users = model(UserModel::class);
        $suffix = bin2hex(random_bytes(4));
        $adminId = (int) $users->insert(new User([
            'username' => 'receipt-flow-' . $suffix,
            'email' => 'receipt-flow-' . $suffix . '@example.test',
            'password' => 'Fixture-' . bin2hex(random_bytes(16)), 'active' => 1,
        ]));
        $admin = $users->findById($adminId);
        $admin->syncGroups('administrador');
        $clientUserId = (int) $users->insert(new User([
            'username' => 'receipt-portal-' . $suffix,
            'email' => 'receipt-portal-' . $suffix . '@example.test',
            'password' => 'Fixture-' . bin2hex(random_bytes(16)), 'active' => 1,
        ]));
        $clientUser = $users->findById($clientUserId);
        $clientUser->syncGroups('cliente');

        $this->db->table('clientes')->insert([
            'nombre' => 'Cliente del recibo', 'activo' => 1, 'version' => 1,
            'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);
        $clientId = (int) $this->db->insertID();
        $documents = new FinancialDocumentService();
        $draft = $documents->createDraft($adminId, $clientId, [
            'type' => 'FACTURA', 'number' => 'FLOW-' . $suffix,
            'issued_on' => '2026-09-18', 'concept' => 'Servicio de flujo',
            'lines' => [['description' => 'Servicio', 'quantity' => '1', 'amount' => '20.00']],
        ], 'receipt-flow-draft-' . $suffix);
        $confirmed = $documents->confirm($adminId, (int) $draft['obligation_id'], ['2026-10-18'], 'receipt-flow-confirm-' . $suffix);
        $this->db->table('cuentas_bancarias')->insert([
            'institucion' => 'Banco QA', 'numero_cuenta' => 'FLOW-' . $suffix,
            'alias' => 'Cobros', 'activa' => 1,
        ]);
        $result = (new PaymentService())->confirmBankTransfer($adminId, $clientId, [
            'amount' => '20.00', 'bank_account_id' => (int) $this->db->insertID(),
            'reference' => 'REF-FLOW', 'bank_date' => '2026-09-18',
            'applications' => [['installment_id' => (int) $confirmed['installments'][0]['id'], 'amount' => '20.00']],
        ], 'receipt-flow-payment-' . $suffix);

        return [
            'admin' => $admin, 'client' => $clientUser,
            'receipt_id' => (int) $result['receipt_id'], 'payment_id' => (int) $result['payment_id'],
            'code' => (string) $result['receipt_code'],
        ];
    }

    public function testAuthorizedUserCanViewReceiptFromPaymentAndDownloadPdfCopy(): void
    {
        $fixture = $this->fixture();
        $payment = $this->actingAs($fixture['admin'])->get('/pagos/' . $fixture['payment_id']);
        $payment->assertStatus(200);
        $this->assertStringContainsString('/recibos/' . $fixture['receipt_id'], $payment->response()->getBody());

        $detail = $this->actingAs($fixture['admin'])->get('/recibos/' . $fixture['receipt_id']);
        $detail->assertStatus(200);
        $this->assertStringContainsString($fixture['code'], $detail->response()->getBody());
        $this->assertStringContainsString('Cliente del recibo', $detail->response()->getBody());

        $pdf = $this->actingAs($fixture['admin'])->get('/recibos/' . $fixture['receipt_id'] . '/pdf');
        $pdf->assertStatus(200);
        $this->assertStringStartsWith('application/pdf', $pdf->response()->getHeaderLine('Content-Type'));
        $this->assertGreaterThan(1500, $pdf->response()->getContentLength());
    }

    public function testPortalClientCannotUseReceiptRoutes(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['client'])->get('/recibos/' . $fixture['receipt_id'])->assertStatus(403);
        $this->actingAs($fixture['client'])->get('/recibos/' . $fixture['receipt_id'] . '/pdf')->assertStatus(403);
    }
}
