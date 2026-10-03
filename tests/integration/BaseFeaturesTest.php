<?php
namespace Tests\Integration;
use App\Services\ClientService;
use App\Services\Access;
use App\Services\CatalogService;
use App\Domain\ValidationException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Shield\Test\AuthenticationTesting;
final class BaseFeaturesTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticationTesting;
    protected $namespace = null;
    protected $refresh = true;
    protected function account(string $group): User
    {
        $users = model(UserModel::class);
        $id = $users->insert(new User(['username'=>$group, 'email'=>$group.'@example.test', 'password'=>'Fixture-only-'.bin2hex(random_bytes(16)), 'active'=>1]));
        $user = $users->findById($id);
        $user->syncGroups($group);
        return $user;
    }
    public function testClientPersistsIdentityContactsAndAuditTogether(): void
    {
        $admin = $this->account('administrador');
        $this->assertTrue(class_exists(ClientService::class));
        $service = new ClientService();
        $id = $service->save((int)$admin->id, ['nombre'=>'María Pérez', 'identificacion'=>'AB-123', 'tipo'=>'PASAPORTE', 'email'=>'maria@example.test', 'saldo'=>'500']);
        $this->assertSame('María Pérez', $service->find($id)['nombre']);
        $this->assertSame('AB123', $service->find($id)['identificacion']);
        $this->seeInDatabase('cliente_contactos', ['cliente_id'=>$id, 'valor'=>'maria@example.test']);
        $this->seeInDatabase('auditoria_eventos', ['accion'=>'clientes.crear','usuario_id'=>$admin->id]);
        $this->assertFalse($this->db->fieldExists('saldo', 'clientes'));
    }
    public function testDuplicateIdentityRollsBackWithoutOrphanClient(): void
    {
        $admin = $this->account('administrador');
        $this->assertTrue(class_exists(ClientService::class));
        $s = new ClientService();
        $s->save((int)$admin->id, ['nombre'=>'Primero', 'identificacion'=>'AB123']);
        try { $s->save((int)$admin->id, ['nombre'=>'Duplicado','identificacion'=>'ab-123']); $this->fail('Duplicó identidad'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('identificacion', $e->errors); }
        $this->assertSame(1, $this->db->table('clientes')->countAllResults());
    }
    public function testCashierCannotCreateUnlessIndividuallyGrantedAndRevocationIsImmediate(): void
    {
        $cashier = $this->account('cajera');
        $this->assertTrue(class_exists(Access::class));
        $this->assertFalse(Access::can((int)$cashier->id,'clientes.crear'));
        $cashier->addPermission('clientes.crear');
        $this->assertTrue(Access::can((int)$cashier->id,'clientes.crear'));
        $cashier->removePermission('clientes.crear');
        $this->assertFalse(Access::can((int)$cashier->id,'clientes.crear'));
    }
    public function testPortalUserCannotGainInternalAccessViaPermission(): void
    {
        $user=$this->account('cliente'); $user->addPermission('clientes.crear');
        $this->assertTrue(class_exists(Access::class));
        $this->assertFalse(Access::can((int)$user->id,'clientes.crear'));
    }
    public function testStaleEditDoesNotOverwriteNewerClientAndDeactivationPreservesClient(): void
    {
        $admin=$this->account('administrador');
        $this->assertTrue(class_exists(ClientService::class));
        $s=new ClientService(); $id=$s->save((int)$admin->id,['nombre'=>'Original']);
        $s->save((int)$admin->id,['nombre'=>'Actualizado','version'=>'1'],$id);
        try {$s->save((int)$admin->id,['nombre'=>'Obsoleto','version'=>'1'],$id); $this->fail('Sobrescribió cambio');}
        catch (ValidationException) { $this->assertSame('Actualizado',$s->find($id)['nombre']); }
        $s->setActive((int)$admin->id,$id,false);
        $this->assertSame('0',(string)$s->find($id)['activo']);
    }
    public function testCatalogStoresExactDecimalAndCashierCannotManageIt(): void
    {
        $admin=$this->account('administrador'); $cashier=$this->account('cajera');
        $this->assertTrue(class_exists(CatalogService::class));
        $id=(new CatalogService())->save((int)$admin->id,['codigo'=>'MENSUAL','nombre'=>'Servicio mensual','tipo'=>'SERVICIO','precio_referencia'=>'19.9']);
        $this->seeInDatabase('items',['id'=>$id,'precio_referencia'=>'19.90']);
        $this->assertFalse(Access::can((int)$cashier->id,'catalogo.gestionar'));
    }
    public function testGuestCannotReadClientsAndRegistrationIsAbsent(): void
    {
        $this->get('/clientes')->assertRedirectTo('/login');
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->get('/register');
    }
    public function testClientListEscapesUserTextAndPortalIsDenied(): void
    {
        $admin=$this->account('administrador');
        $this->assertTrue(class_exists(ClientService::class));
        (new ClientService())->save((int)$admin->id,['nombre'=>'<script>alert(1)</script>']);
        $response=$this->actingAs($admin)->get('/clientes');
        $response->assertStatus(200);
        $this->assertStringContainsString('&lt;script&gt;', $response->response()->getBody());
        auth()->logout();
        $this->actingAs($this->account('cliente'))->get('/clientes')->assertStatus(403);
    }

    public function testInternalPagesExposeTheSharedVisualShellAndPageHeader(): void
    {
        $admin = $this->account('administrador');

        foreach (['/', '/clientes', '/clientes/nuevo', '/catalogo', '/catalogo/nuevo'] as $path) {
            $response = $this->actingAs($admin)->get($path);
            $response->assertStatus(200);
            $body = $response->response()->getBody();
            $this->assertStringContainsString('data-theme="cobros"', $body, $path);
            $this->assertStringContainsString('class="drawer lg:drawer-open"', $body, $path);
            $this->assertStringContainsString('class="page-header', $body, $path);
            $this->assertStringContainsString('data-page-pattern=', $body, $path);
        }
    }

    public function testAdministratorDashboardSummarizesAvailableOperations(): void
    {
        $admin = $this->account('administrador');
        $now = gmdate('Y-m-d H:i:s');

        $this->db->table('clientes')->insertBatch([
            ['nombre' => 'Cliente activo A', 'activo' => 1, 'version' => 1, 'creado_en' => $now],
            ['nombre' => 'Cliente activo B', 'activo' => 1, 'version' => 1, 'creado_en' => $now],
            ['nombre' => 'Cliente inactivo', 'activo' => 0, 'version' => 1, 'creado_en' => $now],
        ]);
        $this->db->table('items')->insertBatch([
            ['codigo' => 'SERV-01', 'tipo' => 'SERVICIO', 'nombre' => 'Servicio activo', 'precio_referencia' => '10.00', 'activo' => 1, 'version' => 1],
            ['codigo' => 'PROD-01', 'tipo' => 'PRODUCTO', 'nombre' => 'Producto inactivo', 'precio_referencia' => '20.00', 'activo' => 0, 'version' => 1],
        ]);
        $this->db->table('auditoria_eventos')->insert([
            'usuario_id' => $admin->id,
            'registrado_en' => $now,
            'accion' => 'catalogo.guardar',
            'referencia_textual' => 'item:2',
            'detalle_sanitizado' => '{}',
        ]);

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('<title>Inicio · Cobros</title>', $body);
        $this->assertStringContainsString('data-dashboard="admin"', $body);
        $this->assertStringContainsString('data-metric="clients-total">3</', $body);
        $this->assertStringContainsString('data-metric="clients-active">2</', $body);
        $this->assertStringContainsString('data-metric="catalog-active">1 de 2</', $body);
        $this->assertStringContainsString('Actividad reciente', $body);
        $this->assertStringContainsString('Actualizaste el catálogo', $body);
        $this->assertStringContainsString('break-words font-semibold', $body);
        $this->assertStringNotContainsString('truncate font-semibold', $body);
        $this->assertStringContainsString('list-col-grow min-w-0', $body);
        $this->assertStringContainsString('[grid-row:2]', $body);
        $this->assertStringContainsString('clientes&#x2F;nuevo', $body);
        $this->assertStringContainsString('catalogo/nuevo', $body);
    }

    public function testClientEditorOpensAsAnAccessibleSemanticModal(): void
    {
        $admin = $this->account('administrador');

        $response = $this->actingAs($admin)->get('/clientes/nuevo');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('<dialog id="client-form-modal"', $body);
        $this->assertStringContainsString('aria-labelledby="client-form-title"', $body);
        $this->assertStringContainsString('aria-describedby="client-form-description"', $body);
        $this->assertStringContainsString('data-auto-open="true"', $body);
        $this->assertStringContainsString('<fieldset', $body);
        $this->assertStringContainsString('class="modal-action', $body);
    }

    public function testCatalogFormExposesBreadcrumbsAndSemanticFieldGroups(): void
    {
        $admin = $this->account('administrador');

        $response = $this->actingAs($admin)->get('/catalogo/nuevo');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('aria-label="Migas de pan"', $body);
        $this->assertStringContainsString('<fieldset', $body);
        $this->assertStringContainsString('class="form-actions', $body);
    }

    public function testStandaloneAccessPagesUseTheCobrosTheme(): void
    {
        auth()->logout();
        $response = $this->get('/login');
        $response->assertStatus(200);
        $login = $response->response()->getBody();
        $this->assertStringContainsString('data-theme="cobros"', $login);

        auth()->logout();
        $response = $this->actingAs($this->account('cliente'))->get('/clientes');
        $response->assertStatus(403);
        $denied = $response->response()->getBody();
        $this->assertStringContainsString('data-theme="cobros"', $denied);
        $this->assertStringContainsString('class="access-shell', $denied);
    }

    public function testSupportingTextOnLightSurfacesUsesAccessibleContrastTokens(): void
    {
        $viewFiles = [
            APPPATH . 'Views/auth/login.php',
            APPPATH . 'Views/catalog/form.php',
            APPPATH . 'Views/catalog/index.php',
            APPPATH . 'Views/clients/form.php',
            APPPATH . 'Views/clients/index.php',
            APPPATH . 'Views/dashboard.php',
            APPPATH . 'Views/partials/empty_state.php',
            APPPATH . 'Views/partials/page_header.php',
            APPPATH . 'Views/partials/topbar.php',
        ];

        foreach ($viewFiles as $file) {
            $contents = file_get_contents($file);
            $this->assertIsString($contents, $file);
            $this->assertDoesNotMatchRegularExpression(
                '/text-base-content\/(?:55|60|65)\b/',
                $contents,
                $file . ' uses a muted text token below 4.5:1 on a light surface.'
            );
        }
    }

    public function testSidebarCompactionDoesNotAnimateLayoutWidth(): void
    {
        $layout = file_get_contents(ROOTPATH . 'resources/css/layout.css');
        $this->assertIsString($layout);
        $this->assertStringNotContainsString(
            'transition: width',
            $layout,
            'Animating sidebar width causes layout work and contradicts the visual architecture.'
        );
    }
}
