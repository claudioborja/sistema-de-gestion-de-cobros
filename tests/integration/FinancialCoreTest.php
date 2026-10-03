<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\InstallmentSchedule;
use App\Domain\ValidationException;
use App\Services\FinancialDocumentService;
use App\Services\PaymentService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class FinancialCoreTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $refresh = true;

    private function administrator(): User
    {
        $users = model(UserModel::class);
        $id = $users->insert(new User([
            'username' => 'finance-admin-' . bin2hex(random_bytes(4)),
            'email' => 'finance-' . bin2hex(random_bytes(4)) . '@example.test',
            'password' => 'Fixture-only-' . bin2hex(random_bytes(16)),
            'active' => 1,
        ]));
        $user = $users->findById($id);
        $user->syncGroups('administrador');

        return $user;
    }

    private function client(string $name = 'Cliente financiero'): int
    {
        $this->db->table('clientes')->insert([
            'nombre' => $name,
            'activo' => 1,
            'version' => 1,
            'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    public function testFinancialLedgerDoesNotStoreEditableBalanceColumns(): void
    {
        $this->assertFalse($this->db->fieldExists('saldo', 'clientes'));
    }

    public function testEqualScheduleKeepsExactTotalWithoutFloats(): void
    {
        $this->assertTrue(class_exists(InstallmentSchedule::class));

        $this->assertSame([
            ['number' => 1, 'due_date' => '2026-10-01', 'amount' => '33.33'],
            ['number' => 2, 'due_date' => '2026-11-01', 'amount' => '33.33'],
            ['number' => 3, 'due_date' => '2026-12-01', 'amount' => '33.34'],
        ], InstallmentSchedule::equal('100.00', ['2026-10-01', '2026-11-01', '2026-12-01']));
    }

    public function testDraftHasNoDebtAndConfirmationCreatesOneScheduleOnly(): void
    {
        $this->assertTrue(class_exists(FinancialDocumentService::class));
        $admin = $this->administrator();
        $clientId = $this->client();
        $service = new FinancialDocumentService();

        $draft = $service->createDraft((int) $admin->id, $clientId, [
            'type' => 'FACTURA',
            'number' => ' FAC-001 ',
            'issued_on' => '2026-09-13',
            'concept' => 'Servicios de septiembre',
            'lines' => [
                ['description' => 'Servicio A', 'quantity' => '1.0000', 'amount' => '20.00'],
                ['description' => 'Servicio B', 'quantity' => '1.0000', 'amount' => '30.00'],
                ['description' => 'Servicio C', 'quantity' => '1.0000', 'amount' => '50.00'],
            ],
        ], 'draft-00000001');

        $this->assertSame('100.00', $draft['amount']);
        $this->assertSame('0.00', $service->balance((int) $draft['obligation_id']));
        $this->dontSeeInDatabase('cuotas', ['obligacion_id' => $draft['obligation_id']]);

        $confirmed = $service->confirm((int) $admin->id, (int) $draft['obligation_id'], [
            '2026-10-01',
            '2026-11-01',
            '2026-12-01',
        ], 'confirm-00000001');
        $replayed = $service->confirm((int) $admin->id, (int) $draft['obligation_id'], [
            '2026-10-01',
            '2026-11-01',
            '2026-12-01',
        ], 'confirm-00000001');

        $this->assertEquals($confirmed, $replayed);
        $this->assertSame('100.00', $service->balance((int) $draft['obligation_id']));
        $this->assertSame(3, $this->db->table('cuotas')->where('obligacion_id', $draft['obligation_id'])->countAllResults());
    }

    public function testPaymentIsIdempotentAndLeavesUnappliedAmountAvailable(): void
    {
        $this->assertTrue(class_exists(PaymentService::class));
        $admin = $this->administrator();
        $clientId = $this->client();
        $document = new FinancialDocumentService();
        $draft = $document->createDraft((int) $admin->id, $clientId, [
            'type' => 'FACTURA', 'number' => 'FAC-PAGO-1', 'issued_on' => '2026-09-13', 'concept' => 'Servicio',
            'lines' => [['description' => 'Servicio', 'quantity' => '1', 'amount' => '100.00']],
        ], 'draft-payment-01');
        $confirmed = $document->confirm((int) $admin->id, (int) $draft['obligation_id'], ['2026-09-30'], 'confirm-payment-01');
        $installmentId = (int) $confirmed['installments'][0]['id'];
        $this->db->table('cuentas_bancarias')->insert(['institucion' => 'Banco prueba', 'numero_cuenta' => '0001', 'alias' => 'Principal', 'activa' => 1]);
        $accountId = (int) $this->db->insertID();
        $payments = new PaymentService();

        $result = $payments->confirmBankTransfer((int) $admin->id, $clientId, [
            'amount' => '120.00',
            'bank_account_id' => $accountId,
            'reference' => 'TRX-001',
            'bank_date' => '2026-09-13',
            'applications' => [['installment_id' => $installmentId, 'amount' => '100.00']],
        ], 'payment-00000001');
        $replayed = $payments->confirmBankTransfer((int) $admin->id, $clientId, [
            'amount' => '120.00',
            'bank_account_id' => $accountId,
            'reference' => 'TRX-001',
            'bank_date' => '2026-09-13',
            'applications' => [['installment_id' => $installmentId, 'amount' => '100.00']],
        ], 'payment-00000001');

        $this->assertEquals($result, $replayed);
        $this->assertSame('20.00', $result['available']);
        $this->assertSame('0.00', $document->balance((int) $draft['obligation_id']));
        $this->assertSame(1, $this->db->table('pagos')->countAllResults());
        $this->assertSame(1, $this->db->table('aplicaciones_pago')->countAllResults());
    }

    public function testPaymentRejectsCrossClientApplications(): void
    {
        $this->assertTrue(class_exists(PaymentService::class));
        $admin = $this->administrator();
        $firstClient = $this->client('Cliente A');
        $secondClient = $this->client('Cliente B');
        $document = new FinancialDocumentService();
        $draft = $document->createDraft((int) $admin->id, $secondClient, [
            'type' => 'FACTURA', 'number' => 'FAC-B', 'issued_on' => '2026-09-13', 'concept' => 'Servicio B',
            'lines' => [['description' => 'Servicio B', 'quantity' => '1', 'amount' => '50.00']],
        ], 'draft-client-b');
        $confirmed = $document->confirm((int) $admin->id, (int) $draft['obligation_id'], ['2026-09-30'], 'confirm-client-b');
        $installmentId = (int) $confirmed['installments'][0]['id'];
        $this->db->table('cuentas_bancarias')->insert(['institucion' => 'Banco prueba', 'numero_cuenta' => '0002', 'alias' => 'Principal', 'activa' => 1]);
        $accountId = (int) $this->db->insertID();
        $payments = new PaymentService();
        $input = [
            'amount' => '50.00', 'bank_account_id' => $accountId, 'reference' => 'TRX-X', 'bank_date' => '2026-09-13',
            'applications' => [['installment_id' => $installmentId, 'amount' => '10.00']],
        ];

        try {
            $payments->confirmBankTransfer((int) $admin->id, $firstClient, $input, 'payment-cross-client');
            $this->fail('Permitió aplicar un pago entre clientes.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('applications', $e->errors);
        }

        $valid = $input;
        $valid['applications'] = [];
        $payments->confirmBankTransfer((int) $admin->id, $firstClient, $valid, 'payment-conflict-key');
        $valid['amount'] = '51.00';
        try {
            $payments->confirmBankTransfer((int) $admin->id, $firstClient, $valid, 'payment-conflict-key');
            $this->fail('Reutilizó una clave idempotente con contenido distinto.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors);
        }
    }

    public function testARejectedDuplicateDoesNotPoisonTheNextFinancialTransaction(): void
    {
        $admin = $this->administrator();
        $clientId = $this->client();
        $service = new FinancialDocumentService();
        $input = [
            'type' => 'FACTURA',
            'number' => 'FAC-RECOVERY-1',
            'issued_on' => '2026-09-13',
            'concept' => 'Primera operación',
            'lines' => [['description' => 'Servicio', 'quantity' => '1', 'amount' => '25.00']],
        ];
        $service->createDraft((int) $admin->id, $clientId, $input, 'recovery-first-01');

        try {
            $service->createDraft((int) $admin->id, $clientId, $input, 'recovery-duplicate');
            $this->fail('El documento duplicado fue aceptado.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('number', $error->errors);
        }

        $input['number'] = 'FAC-RECOVERY-2';
        $input['concept'] = 'Operación posterior válida';
        $result = $service->createDraft((int) $admin->id, $clientId, $input, 'recovery-second-02');

        $this->assertSame('25.00', $result['amount']);
        $this->assertSame(2, $this->db->table('obligaciones')->where('cliente_id', $clientId)->countAllResults());
    }
}
