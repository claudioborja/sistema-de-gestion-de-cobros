<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FinancialDocumentService;
use App\Services\PaymentService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class PaymentFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    protected $refresh = true;

    private function account(string $group = 'administrador'): User
    {
        $username = substr($group, 0, 5) . '-pay-' . bin2hex(random_bytes(3));
        $users = model(UserModel::class);
        $id = $users->insert(new User([
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => 'Fixture-only-' . bin2hex(random_bytes(16)),
            'active' => 1,
        ]));
        $user = $users->findById($id);
        $user->syncGroups($group);

        return $user;
    }

    /** @return array{admin: User, client_id: int, obligation_id: int, installment_id: int, account_id: int} */
    private function receivable(): array
    {
        $admin = $this->account();
        $this->db->table('clientes')->insert([
            'nombre' => 'Cliente con cartera',
            'activo' => 1,
            'version' => 1,
            'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);
        $clientId = (int) $this->db->insertID();
        $documents = new FinancialDocumentService();
        $draft = $documents->createDraft((int) $admin->id, $clientId, [
            'type' => 'FACTURA',
            'number' => 'FAC-PAY-' . bin2hex(random_bytes(3)),
            'issued_on' => '2026-09-13',
            'concept' => 'Servicio con saldo pendiente',
            'lines' => [['description' => 'Servicio', 'quantity' => '1', 'amount' => '100.00']],
        ], 'fixture-draft-' . bin2hex(random_bytes(4)));
        $confirmed = $documents->confirm(
            (int) $admin->id,
            (int) $draft['obligation_id'],
            ['2026-10-13'],
            'fixture-confirm-' . bin2hex(random_bytes(4))
        );
        $this->db->table('cuentas_bancarias')->insert([
            'institucion' => 'Banco de pruebas',
            'numero_cuenta' => 'QA-' . bin2hex(random_bytes(4)),
            'alias' => 'Cuenta principal',
            'activa' => 1,
        ]);

        return [
            'admin' => $admin,
            'client_id' => $clientId,
            'obligation_id' => (int) $draft['obligation_id'],
            'installment_id' => (int) $confirmed['installments'][0]['id'],
            'account_id' => (int) $this->db->insertID(),
        ];
    }

    /** @return array<string, mixed> */
    private function paymentPayload(array $fixture, string $key = 'http-payment-0001', string $amount = '40.00'): array
    {
        return [
            csrf_token() => csrf_hash(),
            'cliente_id' => (string) $fixture['client_id'],
            'idempotency_key' => $key,
            'amount' => $amount,
            'bank_account_id' => (string) $fixture['account_id'],
            'reference' => 'TRX-HTTP-001',
            'bank_date' => '2026-09-13',
            'applications' => [[
                'installment_id' => (string) $fixture['installment_id'],
                'amount' => $amount,
            ]],
        ];
    }

    private function paymentWithAvailableBalance(array $fixture): int
    {
        $payload = $this->paymentPayload($fixture, 'http-payment-available-' . bin2hex(random_bytes(4)));
        $payload['applications'][0]['amount'] = '30.00';
        $this->withSession()->actingAs($fixture['admin'])
            ->post('/clientes/' . $fixture['client_id'] . '/pagos', $payload)
            ->assertRedirect();

        $payment = $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->get()->getRowArray();
        $this->assertNotNull($payment);

        return (int) $payment['id'];
    }

    /** @return array<string, mixed> */
    private function laterApplicationPayload(array $fixture, string $key, string $amount = '10.00'): array
    {
        return [
            csrf_token() => csrf_hash(),
            'idempotency_key' => $key,
            'applications' => [[
                'installment_id' => (string) $fixture['installment_id'],
                'amount' => $amount,
            ]],
        ];
    }

    /** @return array<string, string> */
    private function reversalPayload(string $key, string $reason = 'Transferencia registrada por duplicado.'): array
    {
        return [
            csrf_token() => csrf_hash(),
            'idempotency_key' => $key,
            'reason' => $reason,
        ];
    }

    /** @return array{payment_id: int, application_id: int} */
    private function fullyAppliedPayment(array $fixture, string $key): array
    {
        $this->withSession()->actingAs($fixture['admin'])->post(
            '/clientes/' . $fixture['client_id'] . '/pagos',
            $this->paymentPayload($fixture, $key)
        )->assertRedirect();
        $payment = $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->get()->getRowArray();
        $this->assertNotNull($payment);
        $application = $this->db->table('aplicaciones_pago')->where('pago_id', $payment['id'])->get()->getRowArray();
        $this->assertNotNull($application);

        return ['payment_id' => (int) $payment['id'], 'application_id' => (int) $application['id']];
    }

    /** @return array<string, string> */
    private function applicationReversalPayload(string $key, string $reason = 'La cuota seleccionada no correspondía.'): array
    {
        return [
            csrf_token() => csrf_hash(),
            'idempotency_key' => $key,
            'reason' => $reason,
        ];
    }

    public function testBankTransferServiceRejectsCashierWithoutConfirmationPermission(): void
    {
        $fixture = $this->receivable();
        $cashier = $this->account('cajera');
        $operationCount = $this->db->table('operaciones')->countAllResults();
        $denied = false;

        try {
            (new PaymentService())->confirmBankTransfer(
                (int) $cashier->id,
                $fixture['client_id'],
                $this->paymentPayload($fixture),
                'service-transfer-without-permission'
            );
        } catch (\RuntimeException $error) {
            $this->assertSame(403, $error->getCode());
            $denied = true;
        }

        $this->assertTrue($denied, 'Una cajera sin autorización bancaria no debe confirmar transferencias.');
        $this->assertSame($operationCount, $this->db->table('operaciones')->countAllResults());
        $this->assertSame(0, $this->db->table('pagos')->countAllResults());
        $this->assertSame(0, $this->db->table('aplicaciones_pago')->countAllResults());
        $this->assertSame('100.00', (new FinancialDocumentService())->balance($fixture['obligation_id']));
    }

    public function testBankTransferServiceAllowsCashierWithIndividualConfirmationPermission(): void
    {
        $fixture = $this->receivable();
        $cashier = $this->account('cajera');
        $cashier->addPermission('pagos.bancarios.confirmar');

        $result = (new PaymentService())->confirmBankTransfer(
            (int) $cashier->id,
            $fixture['client_id'],
            $this->paymentPayload($fixture),
            'service-transfer-with-permission'
        );

        $this->seeInDatabase('pagos', ['id' => $result['payment_id'], 'cliente_id' => $fixture['client_id']]);
        $this->seeInDatabase('aplicaciones_pago', [
            'pago_id' => $result['payment_id'],
            'cuota_id' => $fixture['installment_id'],
            'importe' => '40.00',
        ]);
        $this->assertSame('60.00', (new FinancialDocumentService())->balance($fixture['obligation_id']));
    }

    public function testBankTransferPostRejectsCashierWithoutConfirmationPermission(): void
    {
        $fixture = $this->receivable();
        $cashier = $this->account('cajera');

        $response = $this->withSession()->actingAs($cashier)->post(
            '/clientes/' . $fixture['client_id'] . '/pagos',
            $this->paymentPayload($fixture, 'http-transfer-without-permission')
        );

        $response->assertStatus(403);
        $this->assertSame(0, $this->db->table('pagos')->countAllResults());
        $this->assertSame(0, $this->db->table('aplicaciones_pago')->countAllResults());
    }

    public function testBankTransferCreationFormsRequireConfirmationPermission(): void
    {
        $fixture = $this->receivable();
        $cashier = $this->account('cajera');

        $this->actingAs($cashier)->get('/pagos/nuevo')->assertStatus(403);
        $this->actingAs($cashier)->get('/clientes/' . $fixture['client_id'] . '/pagos/nuevo')->assertStatus(403);

        $cashier->addPermission('pagos.bancarios.confirmar');
        $this->actingAs($cashier)->get('/pagos/nuevo')->assertStatus(200);
        $this->actingAs($cashier)->get('/clientes/' . $fixture['client_id'] . '/pagos/nuevo')->assertStatus(200);
    }

    public function testBankTransferConfirmationPermissionDoesNotReplaceCreationPermission(): void
    {
        $fixture = $this->receivable();
        $cashier = $this->account('cajera');
        $cashier->addPermission('pagos.bancarios.confirmar');
        $groups = config('AuthGroups');
        $originalPermissions = $groups->matrix['cajera'];
        $groups->matrix['cajera'] = array_values(array_diff($originalPermissions, ['pagos.crear']));

        try {
            $this->withSession()->actingAs($cashier)->post(
                '/clientes/' . $fixture['client_id'] . '/pagos',
                $this->paymentPayload($fixture, 'http-transfer-without-creation-permission')
            )->assertStatus(403);

            $denied = false;
            try {
                (new PaymentService())->confirmBankTransfer(
                    (int) $cashier->id,
                    $fixture['client_id'],
                    $this->paymentPayload($fixture),
                    'service-transfer-without-creation-permission'
                );
            } catch (\RuntimeException $error) {
                $this->assertSame(403, $error->getCode());
                $denied = true;
            }
            $this->assertTrue($denied);
            $this->assertSame(0, $this->db->table('pagos')->countAllResults());
        } finally {
            $groups->matrix['cajera'] = $originalPermissions;
        }
    }

    public function testPaymentFormShowsRealAccountsAndOpenInstallments(): void
    {
        $fixture = $this->receivable();

        $response = $this->actingAs($fixture['admin'])->get('/clientes/' . $fixture['client_id'] . '/pagos/nuevo');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('name="amount"', $body);
        $this->assertStringContainsString('name="bank_account_id"', $body);
        $this->assertStringContainsString('name="reference"', $body);
        $this->assertStringContainsString('name="bank_date"', $body);
        $this->assertStringContainsString('name="applications[0][installment_id]"', $body);
        $this->assertStringContainsString('name="applications[0][amount]"', $body);
        $this->assertStringContainsString('Cuenta principal', $body);
        $this->assertStringContainsString('$100.00', $body);
        $this->assertStringNotContainsString('El registro financiero aún no está habilitado', $body);
    }

    public function testPaymentApplicationUpdatesDetailHistoryAndPortfolioBalance(): void
    {
        $fixture = $this->receivable();
        $payload = $this->paymentPayload($fixture);

        $created = $this->withSession()->actingAs($fixture['admin'])->post('/clientes/' . $fixture['client_id'] . '/pagos', $payload);
        $created->assertRedirect();
        $payment = $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->get()->getRowArray();
        $this->assertNotNull($payment);
        $paymentId = (int) $payment['id'];
        $this->seeInDatabase('aplicaciones_pago', [
            'pago_id' => $paymentId,
            'cuota_id' => $fixture['installment_id'],
            'importe' => '40.00',
        ]);

        $detail = $this->actingAs($fixture['admin'])->get('/pagos/' . $paymentId);
        $detail->assertStatus(200);
        $body = $detail->response()->getBody();
        $this->assertStringContainsString('TRX-HTTP-001', $body);
        $this->assertStringContainsString('$40.00', $body);
        $this->assertStringContainsString('$0.00', $body);

        $history = $this->actingAs($fixture['admin'])->get('/clientes/' . $fixture['client_id'] . '/pagos');
        $history->assertStatus(200);
        $this->assertStringContainsString('TRX-HTTP-001', $history->response()->getBody());

        $portfolio = $this->actingAs($fixture['admin'])->get('/clientes/' . $fixture['client_id'] . '/cartera');
        $portfolio->assertStatus(200);
        $this->assertStringContainsString('$60.00', $portfolio->response()->getBody());
    }

    public function testPaymentPostIsIdempotentAndRejectsOverApplication(): void
    {
        $fixture = $this->receivable();
        $payload = $this->paymentPayload($fixture, 'http-payment-replay');

        $this->withSession()->actingAs($fixture['admin'])->post('/clientes/' . $fixture['client_id'] . '/pagos', $payload)->assertRedirect();
        $payload[csrf_token()] = csrf_hash();
        $this->withSession()->actingAs($fixture['admin'])->post('/clientes/' . $fixture['client_id'] . '/pagos', $payload)->assertRedirect();
        $this->assertSame(1, $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->countAllResults());
        $this->assertSame(1, $this->db->table('aplicaciones_pago')->countAllResults());

        $excess = $this->paymentPayload($fixture, 'http-payment-excess', '70.00');
        $response = $this->withSession()->actingAs($fixture['admin'])->post('/clientes/' . $fixture['client_id'] . '/pagos', $excess);
        $response->assertRedirect();
        $this->assertSame(1, $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->countAllResults());
    }

    public function testPaymentPostRequiresPermissionAndCsrf(): void
    {
        $fixture = $this->receivable();
        $payload = $this->paymentPayload($fixture, 'http-payment-denied');
        $external = $this->account('cliente');

        $this->actingAs($external)->post('/clientes/' . $fixture['client_id'] . '/pagos', $payload)->assertStatus(403);
        $this->assertSame(0, $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->countAllResults());

        unset($payload[csrf_token()]);
        try {
            $this->actingAs($fixture['admin'])->post('/clientes/' . $fixture['client_id'] . '/pagos', $payload);
            $this->fail('El cobro sin token CSRF fue aceptado.');
        } catch (SecurityException $error) {
            $this->assertStringContainsString('not allowed', $error->getMessage());
        }
        $this->assertSame(0, $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->countAllResults());
    }

    public function testPaymentPostRejectsAnInactiveClientEvenWhenCalledDirectly(): void
    {
        $fixture = $this->receivable();
        $this->db->table('clientes')->where('id', $fixture['client_id'])->update(['activo' => 0]);

        $response = $this->withSession()->actingAs($fixture['admin'])->post(
            '/clientes/' . $fixture['client_id'] . '/pagos',
            $this->paymentPayload($fixture, 'http-payment-inactive')
        );

        $response->assertRedirect();
        $this->assertSame(0, $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->countAllResults());
    }

    public function testPaymentDetailOffersAvailableBalanceForOpenInstallments(): void
    {
        $fixture = $this->receivable();
        $paymentId = $this->paymentWithAvailableBalance($fixture);

        $response = $this->actingAs($fixture['admin'])->get('/pagos/' . $paymentId);
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Aplicar saldo disponible', $body);
        $this->assertStringContainsString('name="applications[0][installment_id]"', $body);
        $this->assertStringContainsString('name="applications[0][amount]"', $body);
        $this->assertStringContainsString('$10.00 disponibles', $body);
        $this->assertStringContainsString('saldo $70.00', $body);
    }

    public function testLaterApplicationUpdatesPaymentAndPortfolioBalances(): void
    {
        $fixture = $this->receivable();
        $paymentId = $this->paymentWithAvailableBalance($fixture);

        $response = $this->withSession()->actingAs($fixture['admin'])->post(
            '/pagos/' . $paymentId . '/aplicaciones',
            $this->laterApplicationPayload($fixture, 'http-later-application-0001')
        );

        $response->assertRedirectTo('/pagos/' . $paymentId);
        $this->assertSame(2, $this->db->table('aplicaciones_pago')->where('pago_id', $paymentId)->countAllResults());
        $this->seeInDatabase('aplicaciones_pago', [
            'pago_id' => $paymentId,
            'cuota_id' => $fixture['installment_id'],
            'importe' => '10.00',
        ]);

        $detail = $this->actingAs($fixture['admin'])->get('/pagos/' . $paymentId);
        $this->assertStringContainsString('$0.00', $detail->response()->getBody());
        $this->assertStringNotContainsString('Aplicar saldo disponible', $detail->response()->getBody());

        $portfolio = $this->actingAs($fixture['admin'])->get('/clientes/' . $fixture['client_id'] . '/cartera');
        $this->assertStringContainsString('$60.00', $portfolio->response()->getBody());
    }

    public function testLaterApplicationIsIdempotent(): void
    {
        $fixture = $this->receivable();
        $paymentId = $this->paymentWithAvailableBalance($fixture);
        $payload = $this->laterApplicationPayload($fixture, 'http-later-application-replay');

        $this->withSession()->actingAs($fixture['admin'])->post('/pagos/' . $paymentId . '/aplicaciones', $payload)->assertRedirect();
        $payload[csrf_token()] = csrf_hash();
        $this->withSession()->actingAs($fixture['admin'])->post('/pagos/' . $paymentId . '/aplicaciones', $payload)->assertRedirect();

        $this->assertSame(2, $this->db->table('aplicaciones_pago')->where('pago_id', $paymentId)->countAllResults());
    }

    public function testLaterApplicationRejectsAnAmountAboveAvailableBalance(): void
    {
        $fixture = $this->receivable();
        $paymentId = $this->paymentWithAvailableBalance($fixture);

        $response = $this->withSession()->actingAs($fixture['admin'])->post(
            '/pagos/' . $paymentId . '/aplicaciones',
            $this->laterApplicationPayload($fixture, 'http-later-application-excess', '10.01')
        );

        $response->assertRedirectTo('/pagos/' . $paymentId);
        $this->assertSame(1, $this->db->table('aplicaciones_pago')->where('pago_id', $paymentId)->countAllResults());
    }

    public function testPaymentReversalFormExplainsTheFinancialEffect(): void
    {
        $fixture = $this->receivable();
        $this->withSession()->actingAs($fixture['admin'])->post(
            '/clientes/' . $fixture['client_id'] . '/pagos',
            $this->paymentPayload($fixture, 'http-payment-for-reversal-form')
        )->assertRedirect();
        $paymentId = (int) $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->get()->getRowArray()['id'];

        $response = $this->actingAs($fixture['admin'])->get('/pagos/' . $paymentId . '/revertir');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Revertir cobro #' . $paymentId, $body);
        $this->assertStringContainsString('name="reason"', $body);
        $this->assertStringContainsString('name="idempotency_key"', $body);
        $this->assertStringContainsString('$40.00', $body);
        $this->assertStringContainsString('La cartera volverá a mostrar $100.00 pendientes', $body);
    }

    public function testPaymentReversalRestoresPortfolioAndKeepsAnAuditTrail(): void
    {
        $fixture = $this->receivable();
        $this->withSession()->actingAs($fixture['admin'])->post(
            '/clientes/' . $fixture['client_id'] . '/pagos',
            $this->paymentPayload($fixture, 'http-payment-for-reversal')
        )->assertRedirect();
        $paymentId = (int) $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->get()->getRowArray()['id'];

        $response = $this->withSession()->actingAs($fixture['admin'])->post(
            '/pagos/' . $paymentId . '/revertir',
            $this->reversalPayload('http-payment-reversal-0001')
        );

        $response->assertRedirectTo('/pagos/' . $paymentId);
        $this->seeInDatabase('pago_reversiones', [
            'pago_id' => $paymentId,
            'clase_correccion' => 'ANULACION_ADMINISTRATIVA',
        ]);
        $reversal = $this->db->table('pago_reversiones')->where('pago_id', $paymentId)->get()->getRowArray();
        $this->assertNotNull($reversal);
        $this->seeInDatabase('operaciones', [
            'id' => $reversal['operacion_id'],
            'motivo' => 'Transferencia registrada por duplicado.',
        ]);
        $this->assertSame(1, $this->db->table('pagos')->where('id', $paymentId)->countAllResults());
        $this->assertSame(1, $this->db->table('aplicaciones_pago')->where('pago_id', $paymentId)->countAllResults());

        $detail = $this->actingAs($fixture['admin'])->get('/pagos/' . $paymentId);
        $detail->assertStatus(200);
        $body = $detail->response()->getBody();
        $this->assertStringContainsString('REVERTIDO', $body);
        $this->assertStringContainsString('Transferencia registrada por duplicado.', $body);
        $this->assertStringNotContainsString('Aplicar saldo disponible', $body);

        $portfolio = $this->actingAs($fixture['admin'])->get('/clientes/' . $fixture['client_id'] . '/cartera');
        $this->assertStringContainsString('$100.00', $portfolio->response()->getBody());
        $this->seeInDatabase('auditoria_eventos', [
            'accion' => 'pagos.revertir',
            'referencia_textual' => 'pago:' . $paymentId,
        ]);
    }

    public function testPaymentReversalIsIdempotent(): void
    {
        $fixture = $this->receivable();
        $this->withSession()->actingAs($fixture['admin'])->post(
            '/clientes/' . $fixture['client_id'] . '/pagos',
            $this->paymentPayload($fixture, 'http-payment-for-reversal-replay')
        )->assertRedirect();
        $paymentId = (int) $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->get()->getRowArray()['id'];
        $payload = $this->reversalPayload('http-payment-reversal-replay');

        $this->withSession()->actingAs($fixture['admin'])->post('/pagos/' . $paymentId . '/revertir', $payload)->assertRedirect();
        $payload[csrf_token()] = csrf_hash();
        $this->withSession()->actingAs($fixture['admin'])->post('/pagos/' . $paymentId . '/revertir', $payload)->assertRedirect();

        $this->assertSame(1, $this->db->table('pago_reversiones')->where('pago_id', $paymentId)->countAllResults());
        $this->assertSame(1, $this->db->table('auditoria_eventos')->where('accion', 'pagos.revertir')->countAllResults());
    }

    public function testPaymentReversalRejectsAnInsufficientReason(): void
    {
        $fixture = $this->receivable();
        $this->withSession()->actingAs($fixture['admin'])->post(
            '/clientes/' . $fixture['client_id'] . '/pagos',
            $this->paymentPayload($fixture, 'http-payment-for-invalid-reversal')
        )->assertRedirect();
        $paymentId = (int) $this->db->table('pagos')->where('cliente_id', $fixture['client_id'])->get()->getRowArray()['id'];

        $response = $this->withSession()->actingAs($fixture['admin'])->post(
            '/pagos/' . $paymentId . '/revertir',
            $this->reversalPayload('http-payment-reversal-invalid', 'Error')
        );

        $response->assertRedirectTo('/pagos/' . $paymentId . '/revertir');
        $this->assertSame(0, $this->db->table('pago_reversiones')->where('pago_id', $paymentId)->countAllResults());
        $portfolio = $this->actingAs($fixture['admin'])->get('/clientes/' . $fixture['client_id'] . '/cartera');
        $this->assertStringContainsString('$60.00', $portfolio->response()->getBody());
    }

    public function testApplicationReversalFormExplainsBothBalanceChanges(): void
    {
        $fixture = $this->receivable();
        $ids = $this->fullyAppliedPayment($fixture, 'http-payment-for-application-form');

        $response = $this->actingAs($fixture['admin'])->get('/aplicaciones/' . $ids['application_id'] . '/revertir');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Revertir aplicación #' . $ids['application_id'], $body);
        $this->assertStringContainsString('name="reason"', $body);
        $this->assertStringContainsString('name="idempotency_key"', $body);
        $this->assertStringContainsString('La cartera volverá a mostrar', $body);
        $this->assertStringContainsString('El saldo disponible del cobro aumentará', $body);
        $this->assertStringContainsString('$100.00', $body);
        $this->assertStringContainsString('$40.00', $body);
    }

    public function testApplicationReversalRestoresPortfolioAndPaymentAvailability(): void
    {
        $fixture = $this->receivable();
        $ids = $this->fullyAppliedPayment($fixture, 'http-payment-for-application-reversal');

        $response = $this->withSession()->actingAs($fixture['admin'])->post(
            '/aplicaciones/' . $ids['application_id'] . '/revertir',
            $this->applicationReversalPayload('http-application-reversal-0001')
        );

        $response->assertRedirectTo('/pagos/' . $ids['payment_id']);
        $this->seeInDatabase('aplicacion_reversiones', ['aplicacion_id' => $ids['application_id']]);
        $this->assertSame(1, $this->db->table('aplicaciones_pago')->where('id', $ids['application_id'])->countAllResults());
        $this->assertSame(0, $this->db->table('pago_reversiones')->where('pago_id', $ids['payment_id'])->countAllResults());

        $detail = $this->actingAs($fixture['admin'])->get('/pagos/' . $ids['payment_id']);
        $detail->assertStatus(200);
        $body = $detail->response()->getBody();
        $this->assertStringContainsString('REVERTIDA', $body);
        $this->assertStringContainsString('La cuota seleccionada no correspondía.', $body);
        $this->assertStringContainsString('$40.00 disponibles', $body);
        $this->assertStringContainsString('Aplicar saldo disponible', $body);

        $portfolio = $this->actingAs($fixture['admin'])->get('/clientes/' . $fixture['client_id'] . '/cartera');
        $this->assertStringContainsString('$100.00', $portfolio->response()->getBody());
        $this->seeInDatabase('auditoria_eventos', [
            'accion' => 'pagos.aplicaciones.revertir',
            'referencia_textual' => 'aplicacion:' . $ids['application_id'],
        ]);
    }

    public function testApplicationReversalIsIdempotent(): void
    {
        $fixture = $this->receivable();
        $ids = $this->fullyAppliedPayment($fixture, 'http-payment-for-application-replay');
        $payload = $this->applicationReversalPayload('http-application-reversal-replay');

        $this->withSession()->actingAs($fixture['admin'])->post('/aplicaciones/' . $ids['application_id'] . '/revertir', $payload)->assertRedirect();
        $payload[csrf_token()] = csrf_hash();
        $this->withSession()->actingAs($fixture['admin'])->post('/aplicaciones/' . $ids['application_id'] . '/revertir', $payload)->assertRedirect();

        $this->assertSame(1, $this->db->table('aplicacion_reversiones')->where('aplicacion_id', $ids['application_id'])->countAllResults());
        $this->assertSame(1, $this->db->table('auditoria_eventos')->where('accion', 'pagos.aplicaciones.revertir')->countAllResults());
    }

    public function testApplicationCannotBeReversedAfterItsPaymentWasReversed(): void
    {
        $fixture = $this->receivable();
        $ids = $this->fullyAppliedPayment($fixture, 'http-payment-for-closed-application');
        $this->withSession()->actingAs($fixture['admin'])->post(
            '/pagos/' . $ids['payment_id'] . '/revertir',
            $this->reversalPayload('http-payment-reversal-before-application')
        )->assertRedirect();

        $response = $this->withSession()->actingAs($fixture['admin'])->post(
            '/aplicaciones/' . $ids['application_id'] . '/revertir',
            $this->applicationReversalPayload('http-application-after-payment-reversal')
        );

        $response->assertRedirectTo('/pagos/' . $ids['payment_id']);
        $this->assertSame(0, $this->db->table('aplicacion_reversiones')->where('aplicacion_id', $ids['application_id'])->countAllResults());
    }

    public function testApplicationReversalRequiresTheDedicatedPermission(): void
    {
        $fixture = $this->receivable();
        $ids = $this->fullyAppliedPayment($fixture, 'http-payment-for-application-permission');
        $cashier = $this->account('cajera');

        $response = $this->actingAs($cashier)->post(
            '/aplicaciones/' . $ids['application_id'] . '/revertir',
            $this->applicationReversalPayload('http-application-reversal-denied')
        );

        $response->assertStatus(403);
        $this->assertSame(0, $this->db->table('aplicacion_reversiones')->where('aplicacion_id', $ids['application_id'])->countAllResults());
    }
}
