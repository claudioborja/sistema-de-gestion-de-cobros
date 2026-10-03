<?php

declare(strict_types=1);

namespace Tests\Integration;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class FinancialDocumentFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    protected $refresh = true;

    private function account(string $group): User
    {
        $username = substr($group, 0, 5) . '-doc-' . bin2hex(random_bytes(3));
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

    private function client(string $name = 'Cliente del flujo'): int
    {
        $this->db->table('clientes')->insert([
            'nombre' => $name,
            'activo' => 1,
            'version' => 1,
            'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /** @return array<string, mixed> */
    private function draftPayload(string $key = 'http-draft-0001', string $number = 'FAC-HTTP-001'): array
    {
        return [
            csrf_token() => csrf_hash(),
            'cliente_id' => '',
            'idempotency_key' => $key,
            'type' => 'FACTURA',
            'number' => $number,
            'issued_on' => '2026-09-13',
            'concept' => 'Servicios integrales de septiembre',
            'lines' => [
                ['description' => 'Implementación', 'quantity' => '1', 'amount' => '70.00'],
                ['description' => 'Soporte', 'quantity' => '2', 'amount' => '30.00'],
            ],
        ];
    }

    public function testNewDocumentFormExposesThePersistedDraftContract(): void
    {
        $admin = $this->account('administrador');
        $clientId = $this->client();

        $response = $this->actingAs($admin)->get('/clientes/' . $clientId . '/documentos/nuevo');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('name="type"', $body);
        $this->assertStringContainsString('name="number"', $body);
        $this->assertStringContainsString('name="issued_on"', $body);
        $this->assertStringContainsString('name="concept"', $body);
        $this->assertStringContainsString('name="lines[0][description]"', $body);
        $this->assertStringContainsString('name="lines[0][amount]"', $body);
        $this->assertStringContainsString('name="idempotency_key"', $body);
        $this->assertStringNotContainsString('El registro financiero aún no está habilitado', $body);
    }

    public function testDraftConfirmationAndPortfolioRunThroughHttp(): void
    {
        $admin = $this->account('administrador');
        $clientId = $this->client('Comercial Horizonte');
        $payload = $this->draftPayload();
        $payload['cliente_id'] = (string) $clientId;

        $created = $this->withSession()->actingAs($admin)->post('/clientes/' . $clientId . '/documentos', $payload);
        $created->assertRedirect();

        $obligation = $this->db->table('obligaciones')->where('cliente_id', $clientId)->get()->getRowArray();
        $this->assertNotNull($obligation);
        $obligationId = (int) $obligation['id'];
        $this->assertSame('100.00', $obligation['importe_base']);
        $this->assertSame(2, $this->db->table('obligacion_detalles')->where('obligacion_id', $obligationId)->countAllResults());

        $detail = $this->actingAs($admin)->get('/documentos/' . $obligationId);
        $detail->assertStatus(200);
        $this->assertStringContainsString('BORRADOR', $detail->response()->getBody());
        $this->assertStringContainsString('$100.00', $detail->response()->getBody());
        $this->assertStringContainsString('$0.00', $detail->response()->getBody());

        $confirmed = $this->withSession()->actingAs($admin)->post('/documentos/' . $obligationId . '/confirmar', [
            csrf_token() => csrf_hash(),
            'idempotency_key' => 'http-confirm-0001',
            'due_dates' => ['2026-10-01', '2026-11-01', '2026-12-01'],
        ]);
        $confirmed->assertRedirect();
        $replayed = $this->withSession()->actingAs($admin)->post('/documentos/' . $obligationId . '/confirmar', [
            csrf_token() => csrf_hash(),
            'idempotency_key' => 'http-confirm-0001',
            'due_dates' => ['2026-10-01', '2026-11-01', '2026-12-01'],
        ]);
        $replayed->assertRedirect();
        $this->assertSame(3, $this->db->table('cuotas')->where('obligacion_id', $obligationId)->countAllResults());

        $portfolio = $this->actingAs($admin)->get('/clientes/' . $clientId . '/cartera');
        $portfolio->assertStatus(200);
        $body = $portfolio->response()->getBody();
        $this->assertStringContainsString('CONFIRMADO', $body);
        $this->assertStringContainsString('$100.00', $body);
        $this->assertStringNotContainsString('No hay saldos ni operaciones verificadas', $body);
    }

    public function testMonthlyCreditPreviewAndConfirmationCreateExactlyTwelveInstallments(): void
    {
        $admin = $this->account('administrador');
        $clientId = $this->client();
        $payload = $this->draftPayload('monthly-draft-01', 'MONTHLY-01');
        $payload['cliente_id'] = (string) $clientId;
        $this->withSession()->actingAs($admin)->post('/clientes/' . $clientId . '/documentos', $payload)->assertRedirect();
        $id = (int) $this->db->table('obligaciones')->where('cliente_id', $clientId)->get()->getRow('id');

        $preview = $this->actingAs($admin)->get('/documentos/' . $id . '?months=12&first_due_on=2026-10-31');
        $preview->assertStatus(200);
        $this->assertStringContainsString('2027-09-30', $preview->response()->getBody());
        $this->assertSame(0, $this->db->table('cuotas')->where('obligacion_id', $id)->countAllResults());

        foreach (['0', '12', '12'] as $months) {
            $this->withSession()->actingAs($admin)->post('/documentos/' . $id . '/confirmar', [
                csrf_token() => csrf_hash(),
                'plan_mode' => 'monthly', 'months' => $months, 'first_due_on' => '2026-10-31',
                'idempotency_key' => 'monthly-confirm-01',
            ])->assertRedirect();
            $this->assertSame($months === '0' ? 0 : 12, $this->db->table('cuotas')->where('obligacion_id', $id)->countAllResults());
        }
        $document = (new \App\Services\FinancialDocumentService())->find($id);
        $this->assertSame('100.00', $document['saldo']);
        $this->assertSame('2027-02-28', $document['cuotas'][4]['fecha_vencimiento']);
        $this->assertSame('2027-03-31', $document['cuotas'][5]['fecha_vencimiento']);
    }

    public function testDraftPostRequiresPermissionAndCsrf(): void
    {
        $clientId = $this->client();
        $external = $this->account('cliente');
        $payload = $this->draftPayload('http-denied-0001', 'FAC-DENIED');
        $payload['cliente_id'] = (string) $clientId;

        $denied = $this->actingAs($external)->post('/clientes/' . $clientId . '/documentos', $payload);
        $denied->assertStatus(403);
        $this->assertSame(0, $this->db->table('obligaciones')->where('cliente_id', $clientId)->countAllResults());

        $admin = $this->account('administrador');
        unset($payload[csrf_token()]);
        try {
            $this->actingAs($admin)->post('/clientes/' . $clientId . '/documentos', $payload);
            $this->fail('El POST sin token CSRF fue aceptado.');
        } catch (SecurityException $error) {
            $this->assertStringContainsString('not allowed', $error->getMessage());
        }
        $this->assertSame(0, $this->db->table('obligaciones')->where('cliente_id', $clientId)->countAllResults());
    }

    public function testDraftCreationIsIdempotentAndRejectsDuplicateDocumentNumbers(): void
    {
        $admin = $this->account('administrador');
        $clientId = $this->client();
        $payload = $this->draftPayload('http-replay-0001', 'FAC-UNICA-001');
        $payload['cliente_id'] = (string) $clientId;

        $this->withSession()->actingAs($admin)->post('/clientes/' . $clientId . '/documentos', $payload)->assertRedirect();
        $payload[csrf_token()] = csrf_hash();
        $this->withSession()->actingAs($admin)->post('/clientes/' . $clientId . '/documentos', $payload)->assertRedirect();
        $this->assertSame(1, $this->db->table('obligaciones')->where('cliente_id', $clientId)->countAllResults());

        $duplicate = $this->draftPayload('http-duplicate-0002', 'FAC UNICA 001');
        $duplicate['cliente_id'] = (string) $clientId;
        $response = $this->withSession()->actingAs($admin)->post('/clientes/' . $clientId . '/documentos', $duplicate);
        $response->assertRedirect();
        $this->assertSame(1, $this->db->table('obligaciones')->where('cliente_id', $clientId)->countAllResults());
    }
}
