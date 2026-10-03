<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ConfigurationService;
use App\Services\PaymentService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class BankConfigurationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    protected $namespace = null;
    protected $refresh = true;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    private function account(string $group = 'administrador'): User
    {
        $users = model(UserModel::class);
        $id = $users->insert(new User(['username' => 'bank-' . bin2hex(random_bytes(4)),
            'email' => bin2hex(random_bytes(6)) . '@example.test', 'password' => 'Test-only-password-2026!', 'active' => 1]));
        $user = $users->findById($id);
        $user->syncGroups($group);
        return $user;
    }

    private function payload(): array
    {
        return [csrf_token() => csrf_hash(), 'institucion' => 'Banco de pruebas', 'numero_cuenta' => '0012345',
            'alias' => 'Recaudación', 'titular' => 'Mi empresa', 'tipo' => 'AHORROS'];
    }

    public function testAccountIsAvailableForPaymentsAndCanBeDeactivatedWithoutDeletingIt(): void
    {
        $admin = $this->account();
        $this->withSession()->actingAs($admin)->post('/configuracion/pagos', $this->payload())->assertRedirect();
        $accounts = (new PaymentService())->activeBankAccounts();
        $this->assertCount(1, $accounts);
        $id = (int) $accounts[0]['id'];
        $this->assertSame('0012345', $accounts[0]['numero_cuenta']);
        $this->assertSame('Mi empresa', $accounts[0]['titular']);
        $this->withSession()->actingAs($admin)->post('/configuracion/pagos', $this->payload())->assertRedirect();
        $this->assertCount(1, (new PaymentService())->activeBankAccounts());

        $payload = $this->payload();
        $payload['alias'] = 'Cuenta principal';
        $this->withSession()->actingAs($admin)->post('/configuracion/cuentas-bancarias/' . $id, $payload)->assertRedirect();
        $this->assertSame('Cuenta principal', (new PaymentService())->activeBankAccounts()[0]['alias']);
        $this->withSession()->actingAs($admin)->post('/configuracion/cuentas-bancarias/' . $id . '/estado', [csrf_token() => csrf_hash(), 'activa' => '0'])->assertRedirect();
        $this->assertCount(0, (new PaymentService())->activeBankAccounts());
        $this->assertSame(1, $this->db->table('cuentas_bancarias')->where('id', $id)->countAllResults());
        $this->withSession()->actingAs($admin)->post('/configuracion/cuentas-bancarias/' . $id . '/estado', [csrf_token() => csrf_hash(), 'activa' => '1'])->assertRedirect();
        $this->assertCount(1, (new PaymentService())->activeBankAccounts());
    }

    public function testCashierCannotChangeBankAccountsAndInvalidInputIsRejected(): void
    {
        $cashier = $this->account('cajera');
        $this->actingAs($cashier)->post('/configuracion/pagos', $this->payload())->assertStatus(403);
        $admin = $this->account();
        $payload = $this->payload();
        $payload['numero_cuenta'] = [];
        $this->withSession()->actingAs($admin)->post('/configuracion/pagos', $payload)->assertRedirect();
        $this->assertCount(0, (new PaymentService())->activeBankAccounts());
    }

    public function testBusinessAndMessageSettingsPersistOutsideTheSession(): void
    {
        $admin = $this->account();
        $service = new ConfigurationService();
        $service->saveBusiness((int) $admin->id, ['business_name' => 'Empresa persistente', 'document_type' => 'No aplica',
            'currency' => 'USD', 'timezone' => 'America/Guayaquil', 'invoice_prefix' => 'FAC', 'invoice_seed' => '1', 'grace_days' => '0']);
        $service->saveMessages((int) $admin->id, ['invoice_subject' => 'Comprobante de prueba', 'invoice_body' => 'Texto persistente de comprobante.',
            'reminder_body' => 'Recordatorio persistente de pago.', 'default_message_method' => 'email']);
        session()->remove(['configuration_business', 'configuration_messages']);
        $fresh = new ConfigurationService();
        $this->assertSame('Empresa persistente', $fresh->business()['business_name']);
        $this->assertSame('Texto persistente de comprobante.', $fresh->messages()['invoice_body']);
    }

    public function testUsedAccountIdentityCannotBeChangedAndInactiveAccountRejectsNewPayments(): void
    {
        $admin = $this->account();
        $service = new \App\Services\BankAccountService();
        $data = $this->payload();
        $id = $service->save((int) $admin->id, null, $data);
        $this->db->table('clientes')->insert(['nombre' => 'Cliente bancario', 'activo' => 1, 'version' => 1, 'creado_en' => gmdate('Y-m-d H:i:s')]);
        $clientId = (int) $this->db->insertID();
        $payment = [csrf_token() => csrf_hash(), 'cliente_id' => (string) $clientId, 'idempotency_key' => 'bank-payment-test-01',
            'amount' => '10.00', 'bank_account_id' => (string) $id, 'reference' => 'BANK-TEST-01', 'bank_date' => '2026-09-18', 'applications' => []];
        $this->withSession()->actingAs($admin)->post('/clientes/' . $clientId . '/pagos', $payment)->assertRedirect();
        $this->assertSame(1, $this->db->table('pagos_bancarios')->where('cuenta_bancaria_id', $id)->countAllResults());
        $data['numero_cuenta'] = '9999999';
        try {
            $service->save((int) $admin->id, $id, $data);
            $this->fail('Cambió la identidad de una cuenta con cobros.');
        } catch (\App\Domain\ValidationException) {
            $this->assertSame('0012345', $service->find((int) $admin->id, $id)['numero_cuenta']);
        }
        $service->setActive((int) $admin->id, $id, '0');
        $payment[csrf_token()] = csrf_hash();
        $payment['idempotency_key'] = 'bank-payment-test-02';
        $payment['reference'] = 'BANK-TEST-02';
        $this->withSession()->actingAs($admin)->post('/clientes/' . $clientId . '/pagos', $payment)->assertRedirect();
        $this->assertSame(1, $this->db->table('pagos_bancarios')->where('cuenta_bancaria_id', $id)->countAllResults());
    }
}
