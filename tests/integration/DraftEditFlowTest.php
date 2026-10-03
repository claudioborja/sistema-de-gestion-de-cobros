<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FinancialDocumentService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class DraftEditFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    protected $refresh = true;

    public function testEditFormLoadsExistingValuesAndDetailLinksToIt(): void
    {
        [$admin, $id] = $this->draft();
        $response = $this->actingAs($admin)->get('/documentos/' . $id . '/editar');
        $response->assertStatus(200);
        $html = html_entity_decode($response->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('value="EDIT-001"', $html);
        $this->assertStringContainsString('value="Servicio original"', $html);
        $this->assertStringContainsString('value="100.00"', $html);
        $this->assertStringContainsString('name="draft_revision"', $html);
        $this->assertStringContainsString('name="idempotency_key"', $html);
        $this->assertStringContainsString('Cliente edición', $html);
        $detail = $this->actingAs($admin)->get('/documentos/' . $id);
        $this->assertStringContainsString('/documentos/' . $id . '/editar', $detail->response()->getBody());
    }

    public function testSavingChangesHeaderAndLinesWithoutCreatingDebt(): void
    {
        [$admin, $id] = $this->draft();
        $payload = $this->payload($id);
        $this->withSession()->actingAs($admin)->post('/documentos/' . $id, $payload)->assertRedirectTo('/documentos/' . $id);
        $document = (new FinancialDocumentService())->find($id);
        $this->assertSame('EDIT-002', $document['numero_completo']);
        $this->assertSame('Concepto corregido', $document['concepto']);
        $this->assertSame('65.00', $document['importe_base']);
        $this->assertSame('Servicio corregido', $document['detalles'][0]['descripcion_pactada']);
        $this->assertSame('0.00', $document['saldo']);
        $this->assertSame([], $document['cuotas']);
    }

    public function testValidationFailurePreservesEnteredFields(): void
    {
        [$admin, $id] = $this->draft();
        $payload = $this->payload($id);
        $payload['lines'][0]['amount'] = '0.00';
        $response = $this->withSession()->actingAs($admin)->post('/documentos/' . $id, $payload);
        $response->assertRedirectTo('/documentos/' . $id . '/editar');
        $response->assertSessionHas('errors');
        $response->assertSessionHas('_ci_old_input');
        $html = html_entity_decode($this->withSession()->actingAs($admin)->get('/documentos/' . $id . '/editar')->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('value="EDIT-002"', $html);
        $this->assertStringContainsString('value="Servicio corregido"', $html);
        $this->assertStringContainsString('value="0.00"', $html);
        $this->assertSame('100.00', (new FinancialDocumentService())->find($id)['importe_base']);
    }

    public function testEditingPreservesExistingCatalogLink(): void
    {
        [$admin, $id] = $this->draft(true);
        $document = (new FinancialDocumentService())->find($id);
        $itemId = (string) $document['detalles'][0]['item_id'];
        $response = $this->actingAs($admin)->get('/documentos/' . $id . '/editar');
        $response->assertStatus(200);
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->response()->getBody());
        $itemFields = (new \DOMXPath($dom))->query('//input[@name="lines[0][item_id]"]');
        $this->assertSame(1, $itemFields->length);
        $payload = $this->payload($id);
        $payload['lines'][0]['item_id'] = $itemFields->item(0)->getAttribute('value');
        $this->assertSame($itemId, $payload['lines'][0]['item_id']);
        $this->withSession()->actingAs($admin)->post('/documentos/' . $id, $payload)->assertRedirectTo('/documentos/' . $id);
        $updated = (new FinancialDocumentService())->find($id);
        $this->assertSame($itemId, (string) $updated['detalles'][0]['item_id']);
    }

    public function testConfirmedDocumentCannotOpenOrSubmitEditing(): void
    {
        [$admin, $id] = $this->draft();
        $payload = $this->payload($id);
        (new FinancialDocumentService())->confirm((int) $admin->id, $id, ['2026-10-18'], 'edit-confirm-first');
        $this->actingAs($admin)->get('/documentos/' . $id . '/editar')->assertRedirectTo('/documentos/' . $id);
        $this->withSession()->actingAs($admin)->post('/documentos/' . $id, $payload)->assertRedirect();
        $this->assertSame('100.00', (new FinancialDocumentService())->find($id)['importe_base']);
        $html = $this->actingAs($admin)->get('/documentos/' . $id)->response()->getBody();
        $this->assertStringNotContainsString('/documentos/' . $id . '/editar', $html);
    }

    public function testExternalClientCannotReadOrSubmitEditing(): void
    {
        [, $id] = $this->draft();
        $external = $this->account('cliente');
        $this->actingAs($external)->get('/documentos/' . $id . '/editar')->assertStatus(403);
        $this->withSession()->actingAs($external)->post('/documentos/' . $id, $this->payload($id))->assertStatus(403);
        $this->assertSame('100.00', (new FinancialDocumentService())->find($id)['importe_base']);
    }

    public function testEditingRequiresCsrf(): void
    {
        [$admin, $id] = $this->draft();
        $payload = $this->payload($id);
        unset($payload[csrf_token()]);
        try {
            $this->actingAs($admin)->post('/documentos/' . $id, $payload);
            $this->fail('Se aceptó la edición sin CSRF.');
        } catch (SecurityException $error) {
            $this->assertStringContainsString('not allowed', $error->getMessage());
        }
        $this->assertSame('100.00', (new FinancialDocumentService())->find($id)['importe_base']);
    }

    private function account(string $group): User
    {
        $suffix = bin2hex(random_bytes(4));
        $users = model(UserModel::class);
        $id = $users->insert(new User([
            'username' => 'edit-' . $suffix, 'email' => 'edit-' . $suffix . '@example.test',
            'password' => 'Fixture-only-' . bin2hex(random_bytes(16)), 'active' => 1,
        ]));
        $user = $users->findById($id);
        $user->syncGroups($group);

        return $user;
    }

    private function draft(bool $withCatalog = false): array
    {
        $admin = $this->account('administrador');
        $this->db->table('clientes')->insert([
            'nombre' => 'Cliente edición', 'activo' => 1, 'version' => 1, 'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);
        $clientId = (int) $this->db->insertID();
        $itemId = null;
        if ($withCatalog) {
            $this->db->table('items')->insert(['codigo' => 'EDIT-SERVICE', 'tipo' => 'SERVICIO', 'nombre' => 'Servicio catálogo']);
            $itemId = (int) $this->db->insertID();
        }
        $result = (new FinancialDocumentService())->createDraft((int) $admin->id, $clientId, [
            'type' => 'FACTURA', 'number' => 'EDIT-001', 'issued_on' => '2026-09-18', 'concept' => 'Concepto original',
            'lines' => [['description' => 'Servicio original', 'quantity' => '1', 'amount' => '100.00', 'item_id' => $itemId]],
        ], 'edit-fixture-draft');

        return [$admin, (int) $result['obligation_id']];
    }

    private function payload(int $id): array
    {
        $document = (new FinancialDocumentService())->find($id);

        return [
            csrf_token() => csrf_hash(), 'idempotency_key' => 'edit-http-update',
            'draft_revision' => $document['draft_revision'] ?? '',
            'type' => 'NOTA_VENTA', 'number' => 'EDIT-002', 'issued_on' => '2026-09-17', 'concept' => 'Concepto corregido',
            'lines' => [['description' => 'Servicio corregido', 'quantity' => '2', 'amount' => '65.00', 'item_id' => '']],
        ];
    }
}
