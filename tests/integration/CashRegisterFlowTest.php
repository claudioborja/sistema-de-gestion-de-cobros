<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\CashRegisterService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class CashRegisterFlowTest extends CIUnitTestCase
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

    public function testAdministratorCanFindTheEmptyListAndCreationForm(): void
    {
        $admin = $this->account('administrador');
        $this->actingAs($admin)->get('/cajas')->assertStatus(200);
        $form = $this->actingAs($admin)->get('/cajas/nueva');
        $form->assertStatus(200);
        $html = $form->response()->getBody();
        $this->assertStringContainsString('name="codigo"', $html);
        $this->assertStringContainsString('name="nombre"', $html);
        $this->assertStringContainsString('name="activa"', $html);
        $this->assertStringContainsString('name="' . csrf_token() . '"', $html);
    }

    public function testCreateEditAndDeactivatePreserveTheSameRegister(): void
    {
        $admin = $this->account('administrador');
        $this->withSession()->actingAs($admin)->post('/cajas', $this->payload())->assertRedirectTo('/cajas');
        $service = new CashRegisterService();
        $rows = $service->all((int) $admin->id);
        $this->assertCount(1, $rows);
        $id = (int) $rows[0]['id'];
        $this->assertSame('PRINCIPAL', $rows[0]['codigo']);
        $this->assertSame(1, (int) $rows[0]['activa']);
        $form = $this->actingAs($admin)->get('/cajas/' . $id . '/editar');
        $form->assertStatus(200);
        $html = html_entity_decode($form->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('value="Caja principal"', $html);
        $this->assertStringContainsString('name="version"', $html);

        $this->withSession()->actingAs($admin)->post('/cajas/' . $id, $this->payload([
            'nombre' => 'Caja recepción', 'activa' => '0', 'version' => (string) $rows[0]['version'],
        ]))->assertRedirectTo('/cajas');
        $updated = $service->find((int) $admin->id, $id);
        $this->assertSame('Caja recepción', $updated['nombre']);
        $this->assertSame(0, (int) $updated['activa']);
        $this->assertCount(1, $service->all((int) $admin->id));
        $list = $this->actingAs($admin)->get('/cajas');
        $this->assertStringContainsString('Inactiva', $list->response()->getBody());
    }

    public function testValidationPreservesInputAndDoesNotCreateRegister(): void
    {
        $admin = $this->account('administrador');
        $response = $this->withSession()->actingAs($admin)->post('/cajas', $this->payload(['codigo' => '']));
        $response->assertRedirectTo('/cajas/nueva');
        $response->assertSessionHas('errors');
        $response->assertSessionHas('_ci_old_input');
        $form = $this->withSession()->actingAs($admin)->get('/cajas/nueva');
        $html = html_entity_decode($form->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('value="Caja principal"', $html);
        $this->assertSame([], (new CashRegisterService())->all((int) $admin->id));
    }

    public function testStaleFormCannotOverwriteNewerChanges(): void
    {
        $admin = $this->account('administrador');
        $this->withSession()->actingAs($admin)->post('/cajas', $this->payload())->assertRedirectTo('/cajas');
        $service = new CashRegisterService();
        $row = $service->all((int) $admin->id)[0];
        $id = (int) $row['id'];
        $stale = $this->payload(['version' => (string) $row['version'], 'nombre' => 'Edición antigua']);
        $this->withSession()->actingAs($admin)->post('/cajas/' . $id, $this->payload([
            'version' => (string) $row['version'], 'nombre' => 'Edición vigente',
        ]))->assertRedirectTo('/cajas');
        $stale[csrf_token()] = csrf_hash();
        $response = $this->withSession()->actingAs($admin)->post('/cajas/' . $id, $stale);
        $response->assertRedirectTo('/cajas/' . $id . '/editar');
        $response->assertSessionHas('errors');
        $this->assertSame('Edición vigente', $service->find((int) $admin->id, $id)['nombre']);
    }

    public function testCashierAndClientCannotManagePhysicalRegisters(): void
    {
        foreach (['cajera', 'cliente'] as $group) {
            $user = $this->account($group);
            foreach (['/cajas', '/cajas/nueva', '/cajas/1/editar'] as $route) {
                $this->actingAs($user)->get($route)->assertStatus(403);
            }
            foreach (['/cajas', '/cajas/1'] as $route) {
                $this->withSession()->actingAs($user)->post($route, $this->payload(['version' => '1']))->assertStatus(403);
            }
        }
    }

    public function testCreatingRegisterRequiresCsrf(): void
    {
        $admin = $this->account('administrador');
        $payload = $this->payload();
        unset($payload[csrf_token()]);
        try {
            $this->actingAs($admin)->post('/cajas', $payload);
            $this->fail('Se aceptó un alta de caja sin CSRF.');
        } catch (SecurityException $error) {
            $this->assertStringContainsString('not allowed', $error->getMessage());
        }
        $this->assertSame([], (new CashRegisterService())->all((int) $admin->id));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            csrf_token() => csrf_hash(), 'codigo' => 'PRINCIPAL', 'nombre' => 'Caja principal', 'activa' => '1',
        ], $overrides);
    }

    private function account(string $group): User
    {
        $suffix = bin2hex(random_bytes(4));
        $users = model(UserModel::class);
        $id = $users->insert(new User([
            'username' => 'cash-' . $suffix, 'email' => 'cash-' . $suffix . '@example.test',
            'password' => 'Fixture-only-' . bin2hex(random_bytes(16)), 'active' => 1,
        ]));
        $user = $users->findById($id);
        $user->syncGroups($group);

        return $user;
    }
}
