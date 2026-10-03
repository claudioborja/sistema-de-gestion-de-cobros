<?php

namespace Tests\Integration;

use App\Services\CatalogService;
use App\Services\ClientService;
use App\Services\StatementService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class ExpandedViewsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $namespace = null;
    protected $refresh = true;

    private function account(string $group, ?string $username = null, ?string $password = null): User
    {
        $username ??= $group . '-' . bin2hex(random_bytes(3));
        $users = model(UserModel::class);
        $id = $users->insert(new User([
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => $password ?? 'Fixture-only-' . bin2hex(random_bytes(16)),
            'active' => 1,
        ]));
        $user = $users->findById($id);
        $user->syncGroups($group);

        return $user;
    }

    public function testClientRecordShowsIdentityContactsAndHistory(): void
    {
        $admin = $this->account('administrador');
        $id = (new ClientService())->save((int) $admin->id, [
            'nombre' => 'Distribuidora Andina',
            'identificacion' => '1790012345001',
            'tipo' => 'RUC',
            'email' => 'cobros@andina.example',
            'telefono' => '0991234567',
            'direccion' => 'Av. Central 123',
        ]);

        $response = $this->actingAs($admin)->get('/clientes/' . $id . '/expediente');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Expediente del cliente', $body);
        $this->assertStringContainsString('Distribuidora Andina', $body);
        $this->assertStringContainsString('1790012345001', $body);
        $this->assertStringContainsString('cobros@andina.example', $body);
        $this->assertStringContainsString('Actividad del expediente', $body);
    }

    public function testDuplicateReviewFindsSimilarNamesWithoutListingCurrentClient(): void
    {
        $admin = $this->account('administrador');
        $service = new ClientService();
        $current = $service->save((int) $admin->id, ['nombre' => 'Comercial Rivera']);
        $possibleDuplicate = $service->save((int) $admin->id, ['nombre' => 'Comercial Rivera Cía. Ltda.']);
        $service->save((int) $admin->id, ['nombre' => 'Panadería El Trigal']);

        $response = $this->actingAs($admin)->get('/clientes/' . $current . '/duplicados');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Posibles duplicados', $body);
        $this->assertStringContainsString('Comercial Rivera Cía. Ltda.', $body);
        $this->assertStringContainsString('clientes/' . $possibleDuplicate . '/expediente', $body);
        $this->assertStringNotContainsString('Panadería El Trigal', $body);
    }

    public function testClientDataEndpointReturnsFilteredPaginationContract(): void
    {
        $admin = $this->account('administrador');
        $service = new ClientService();
        $service->save((int) $admin->id, ['nombre' => 'Alfa Norte', 'identificacion' => 'ABC-101']);
        $service->save((int) $admin->id, ['nombre' => 'Beta Sur', 'identificacion' => 'XYZ-202']);

        $response = $this->actingAs($admin)->get('/clientes/datos?q=Alfa&estado=1&page=1');
        $response->assertStatus(200);
        $payload = json_decode($response->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame(1, $payload['pagination']['page']);
        $this->assertSame('Alfa Norte', $payload['data'][0]['nombre']);
        $this->assertSame('ABC101', $payload['data'][0]['identificacion']);
    }

    public function testClientDataTableUsesFiveRowsByDefaultAndRejectsUnlistedSizes(): void
    {
        $admin = $this->account('administrador');
        $rows = [];
        for ($number = 1; $number <= 12; $number++) {
            $rows[] = [
                'nombre' => sprintf('Cliente DataTable %02d', $number),
                'activo' => 1,
                'version' => 1,
                'creado_en' => gmdate('Y-m-d H:i:s'),
            ];
        }
        $this->db->table('clientes')->insertBatch($rows);

        $default = $this->actingAs($admin)->get('/clientes/datos?draw=3&start=0');
        $default->assertStatus(200);
        $defaultPayload = json_decode($default->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(3, $defaultPayload['draw']);
        $this->assertCount(5, $defaultPayload['data']);

        $unsupported = $this->actingAs($admin)->get('/clientes/datos?draw=4&start=0&length=50');
        $unsupported->assertStatus(200);
        $unsupportedPayload = json_decode($unsupported->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(5, $unsupportedPayload['data']);
    }

    public function testCatalogDetailShowsCommercialRecord(): void
    {
        $admin = $this->account('administrador');
        $id = (new CatalogService())->save((int) $admin->id, [
            'codigo' => 'SOP-001',
            'nombre' => 'Soporte mensual',
            'tipo' => 'SERVICIO',
            'precio_referencia' => '89.50',
        ]);

        $response = $this->actingAs($admin)->get('/catalogo/' . $id . '/detalle');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Detalle de ítem', $body);
        $this->assertStringContainsString('SOP-001', $body);
        $this->assertStringContainsString('$89.50', $body);
        $this->assertStringContainsString('Servicio', $body);
    }

    public function testAdministrativeDirectoryAndOwnAccountAreUsefulAndProtected(): void
    {
        $admin = $this->account('administrador', 'propietaria');
        $this->account('cajera', 'caja-principal');

        $users = $this->actingAs($admin)->get('/usuarios');
        $users->assertStatus(200);
        $this->assertStringContainsString('Usuarios', $users->response()->getBody());
        $this->assertStringContainsString('caja-principal', $users->response()->getBody());
        $this->assertStringContainsString('Cajera', $users->response()->getBody());

        $account = $this->actingAs($admin)->get('/mi-cuenta');
        $account->assertStatus(200);
        $this->assertStringContainsString('Mi cuenta', $account->response()->getBody());
        $this->assertStringContainsString('propietaria@example.test', $account->response()->getBody());

        auth()->logout();
        $this->actingAs($this->account('cajera'))->get('/usuarios')->assertStatus(403);
    }

    public function testReportsAuditAndOperationalStatusExposeCurrentData(): void
    {
        $admin = $this->account('administrador');
        (new ClientService())->save((int) $admin->id, ['nombre' => 'Cliente de reporte']);
        (new CatalogService())->save((int) $admin->id, [
            'codigo' => 'REP-01',
            'nombre' => 'Ítem de reporte',
            'tipo' => 'SERVICIO',
            'precio_referencia' => '10.00',
        ]);

        $reports = $this->actingAs($admin)->get('/reportes');
        $reports->assertStatus(200);
        $this->assertStringContainsString('Panel de reportes', $reports->response()->getBody());
        $this->assertStringContainsString('data-report="clients">1</', $reports->response()->getBody());

        $audit = $this->actingAs($admin)->get('/operacion/auditoria');
        $audit->assertStatus(200);
        $this->assertStringContainsString('Auditoría', $audit->response()->getBody());
        $this->assertStringContainsString('Cliente de reporte', $audit->response()->getBody());

        $status = $this->actingAs($admin)->get('/operacion/estado');
        $status->assertStatus(200);
        $statusBody = $status->response()->getBody();
        $this->assertStringContainsString('Estado operativo', $statusBody);
        $this->assertStringContainsString('data-health-check="database"', $statusBody);
        $this->assertMatchesRegularExpression('/data-health-check="database"[^>]+data-health-level="success"/', $statusBody);
        $this->assertStringContainsString('data-health-check="email"', $statusBody);
        $this->assertMatchesRegularExpression('/data-health-check="email"[^>]+data-health-level="unknown"/', $statusBody);
        $this->assertStringContainsString('<time datetime=', $statusBody);
    }

    public function testAdministratorCreatesAndEditsAUserWithAnAssignedRole(): void
    {
        $admin = $this->account('administrador');

        $form = $this->actingAs($admin)->get('/usuarios/nuevo');
        $form->assertStatus(200);
        $this->assertStringContainsString('Nuevo usuario', $form->response()->getBody());

        $csrf = csrf_hash();
        $create = $this->withSession()->actingAs($admin)->post('/usuarios', [
            csrf_token() => $csrf,
            'username' => 'cobros.norte',
            'email' => 'norte@example.test',
            'password' => 'Temporal-segura-2026',
            'group' => 'cajera',
        ]);
        $create->assertRedirect();

        $created = model(UserModel::class)->findByCredentials(['email' => 'norte@example.test']);
        $this->assertNotNull($created);
        $this->assertTrue($created->inGroup('cajera'));
        $this->seeInDatabase('auditoria_eventos', ['accion' => 'usuarios.crear', 'referencia_textual' => 'usuario:' . $created->id]);

        $edit = $this->actingAs($admin)->get('/usuarios/' . $created->id);
        $edit->assertStatus(200);
        $this->assertStringContainsString('Editar usuario', $edit->response()->getBody());

        $csrf = csrf_hash();
        $update = $this->withSession()->actingAs($admin)->post('/usuarios/' . $created->id, [
            csrf_token() => $csrf,
            'username' => 'cobros.centro',
            'email' => 'centro@example.test',
            'group' => 'administrador',
        ]);
        $update->assertRedirect();
        $updated = model(UserModel::class)->findById($created->id);
        $this->assertSame('cobros.centro', $updated->username);
        $this->assertTrue($updated->inGroup('administrador'));

        $audit = $this->actingAs($admin)->get('/operacion/auditoria');
        $audit->assertStatus(200);
        $this->assertStringContainsString('cobros.centro', $audit->response()->getBody());
        $this->assertStringNotContainsString('aria-current="page">Editar usuario', $audit->response()->getBody());
    }

    public function testLastActiveAdministratorCannotBeDeactivated(): void
    {
        $admin = $this->account('administrador');

        $csrf = csrf_hash();
        $response = $this->withSession()->actingAs($admin)->post('/usuarios/' . $admin->id . '/estado', [csrf_token() => $csrf, 'active' => '0']);
        $response->assertRedirect();

        $fresh = model(UserModel::class)->findById($admin->id);
        $this->assertTrue((bool) $fresh->active);
        $this->assertSame('Debe permanecer al menos un administrador activo.', session('errors')['active'] ?? null);
    }

    public function testAdministratorAssignsDirectPermissionsAndSeesLastAccess(): void
    {
        $admin = $this->account('administrador');
        $cashier = $this->account('cajera', 'caja.eventual');
        $this->db->table('users')->where('id', $cashier->id)->update(['last_active' => '2026-09-11 15:30:00']);

        $csrf = csrf_hash();
        $response = $this->withSession()->actingAs($admin)->post('/usuarios/' . $cashier->id . '/permisos', [
            csrf_token() => $csrf,
            'permissions' => ['clientes.crear', 'catalogo.gestionar'],
        ]);
        $response->assertRedirect();

        $updated = model(UserModel::class)->findById($cashier->id);
        $this->assertTrue($updated->hasPermission('clientes.crear'));
        $this->assertTrue($updated->hasPermission('catalogo.gestionar'));
        $this->seeInDatabase('auditoria_eventos', ['accion' => 'usuarios.permisos', 'referencia_textual' => 'usuario:' . $cashier->id]);

        $directory = $this->actingAs($admin)->get('/usuarios');
        $directory->assertStatus(200);
        $this->assertStringContainsString('Último acceso', $directory->response()->getBody());
        $this->assertStringContainsString('11/09/2026 10:30', $directory->response()->getBody());
    }

    public function testUserUpdatesOwnIdentityAndChangesPasswordAfterVerification(): void
    {
        $currentPassword = 'Actual-segura-2026';
        $newPassword = 'Nueva-segura-2026';
        $admin = $this->account('administrador', 'perfil.inicial', $currentPassword);

        $csrf = csrf_hash();
        $identity = $this->withSession()->actingAs($admin)->post('/mi-cuenta', [
            csrf_token() => $csrf,
            'username' => 'perfil.actualizado',
            'email' => 'perfil.actualizado@example.test',
        ]);
        $identity->assertRedirect();
        $fresh = model(UserModel::class)->findById($admin->id);
        $this->assertSame('perfil.actualizado', $fresh->username);
        $this->assertSame('perfil.actualizado@example.test', $fresh->email);

        $csrf = csrf_hash();
        $wrong = $this->withSession()->actingAs($fresh)->post('/mi-cuenta/contrasena', [
            csrf_token() => $csrf,
            'current_password' => 'Clave-incorrecta-2026',
            'password' => $newPassword,
            'password_confirm' => $newPassword,
        ]);
        $wrong->assertRedirect();
        $unchanged = model(UserModel::class)->findById($admin->id);
        $this->assertTrue(service('passwords')->verify($currentPassword, $unchanged->password_hash));

        $csrf = csrf_hash();
        $changed = $this->withSession()->actingAs($unchanged)->post('/mi-cuenta/contrasena', [
            csrf_token() => $csrf,
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirm' => $newPassword,
        ]);
        $changed->assertRedirect();
        $final = model(UserModel::class)->findById($admin->id);
        $this->assertTrue(service('passwords')->verify($newPassword, $final->password_hash));
        $this->seeInDatabase('auditoria_eventos', ['accion' => 'usuarios.contrasena', 'referencia_textual' => 'usuario:' . $admin->id]);
    }

    public function testAuditFiltersVisibleRecordsAndPreservesPaginationContext(): void
    {
        $admin = $this->account('administrador');
        $service = new ClientService();
        $service->save((int) $admin->id, ['nombre' => 'Cobranza Alfa']);
        $service->save((int) $admin->id, ['nombre' => 'Cobranza Beta']);

        $response = $this->actingAs($admin)->get('/operacion/auditoria?q=Alfa&accion=clientes.crear&page=1');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('<title>Auditoría · Cobros</title>', $body);
        $this->assertStringContainsString('Cobranza Alfa', $body);
        $this->assertStringNotContainsString('Cobranza Beta', $body);
        $this->assertStringContainsString('value="Alfa"', $body);
        $this->assertStringContainsString('value="clientes.crear" selected', $body);
        $this->assertStringContainsString('>1</span> registros', $body);
    }

    public function testAuditCanFilterARecordedPaymentActionFromTheSelector(): void
    {
        $admin = $this->account('administrador');
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('auditoria_eventos')->insertBatch([
            ['usuario_id' => $admin->id, 'registrado_en' => $now, 'accion' => 'pagos.revertir', 'referencia_textual' => 'pago:41', 'detalle_sanitizado' => '{}'],
            ['usuario_id' => $admin->id, 'registrado_en' => $now, 'accion' => 'clientes.crear', 'referencia_textual' => 'cliente:99', 'detalle_sanitizado' => '{}'],
        ]);

        $response = $this->actingAs($admin)->get('/operacion/auditoria?accion=pagos.revertir');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('value="pagos.revertir" selected', $body);
        $this->assertStringContainsString('Pago revertido', $body);
        $this->assertStringContainsString('pago:41', $body);
        $this->assertStringNotContainsString('Cliente #99', $body);
        $this->assertStringContainsString('>1</span> registros', $body);
    }

    public function testAuditSearchMatchesTheActionLabelShownToTheUser(): void
    {
        $admin = $this->account('administrador');
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('auditoria_eventos')->insertBatch([
            ['usuario_id' => $admin->id, 'registrado_en' => $now, 'accion' => 'pagos.revertir', 'referencia_textual' => 'pago:73', 'detalle_sanitizado' => '{}'],
            ['usuario_id' => $admin->id, 'registrado_en' => $now, 'accion' => 'clientes.crear', 'referencia_textual' => 'cliente:98', 'detalle_sanitizado' => '{}'],
        ]);

        $response = $this->actingAs($admin)->get('/operacion/auditoria?q=Pago%20revertido');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Pago revertido', $body);
        $this->assertStringContainsString('pago:73', $body);
        $this->assertStringNotContainsString('Cliente #98', $body);
        $this->assertStringContainsString('>1</span> registros', $body);
    }

    public function testAuditSelectorIncludesActionsRecordedBySetupAndPaymentWorkflows(): void
    {
        $admin = $this->account('administrador');

        $response = $this->actingAs($admin)->get('/operacion/auditoria');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('value="demo.datos.poblados"', $body);
        $this->assertStringContainsString('value="usuarios.acceso_local_actualizado"', $body);
        $this->assertStringContainsString('value="pagos.registrar"', $body);
    }

    public function testAuditShowsAnEmptyStateWhenAnActionFilterHasNoMatches(): void
    {
        $admin = $this->account('administrador');

        $response = $this->actingAs($admin)->get('/operacion/auditoria?accion=pagos.revertir&q=sin-coincidencias');

        $response->assertStatus(200);
        $this->assertStringContainsString('Aún no hay eventos', $response->response()->getBody());
    }

    public function testAuditDataTableReturnsTheRequestedTenRowPage(): void
    {
        $admin = $this->account('administrador');
        $rows = [];
        for ($number = 1; $number <= 12; $number++) {
            $rows[] = [
                'usuario_id' => $admin->id,
                'registrado_en' => sprintf('2026-09-14 12:%02d:00', $number),
                'accion' => 'pagos.registrar',
                'referencia_textual' => 'pago:' . $number,
                'detalle_sanitizado' => '{}',
            ];
        }
        $this->db->table('auditoria_eventos')->insertBatch($rows);

        $response = $this->actingAs($admin)->get('/operacion/auditoria/datos?draw=7&start=0&length=10&accion=pagos.registrar');
        $response->assertStatus(200);
        $payload = json_decode($response->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(7, $payload['draw']);
        $this->assertSame(12, $payload['recordsTotal']);
        $this->assertSame(12, $payload['recordsFiltered']);
        $this->assertCount(10, $payload['data']);
        $this->assertSame('Pago registrado', $payload['data'][0]['action']);

        $unsupported = $this->actingAs($admin)->get('/operacion/auditoria/datos?draw=8&start=0&length=50&accion=pagos.registrar');
        $unsupported->assertStatus(200);
        $unsupportedPayload = json_decode($unsupported->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(5, $unsupportedPayload['data']);
    }

    public function testAuditPageDeclaresItsRemoteDataTableSource(): void
    {
        $admin = $this->account('administrador');
        $this->db->table('auditoria_eventos')->insert([
            'usuario_id' => $admin->id,
            'registrado_en' => gmdate('Y-m-d H:i:s'),
            'accion' => 'clientes.crear',
            'referencia_textual' => 'cliente:101',
            'detalle_sanitizado' => '{}',
        ]);

        $response = $this->actingAs($admin)->get('/operacion/auditoria');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('data-datatable="audit"', $body);
        $this->assertMatchesRegularExpression('/data-source="[^"]+\/operacion\/auditoria\/datos"/', $body);
    }

    public function testAuditExposesEventDetailsAndClearControlToAdministrators(): void
    {
        $admin = $this->account('administrador');
        $this->db->table('auditoria_eventos')->insert([
            'usuario_id' => $admin->id,
            'registrado_en' => '2026-09-14 16:00:00',
            'accion' => 'clientes.editar',
            'referencia_textual' => 'cliente:44',
            'detalle_sanitizado' => json_encode(['campo' => 'teléfono', 'origen' => 'expediente'], JSON_THROW_ON_ERROR),
        ]);

        $page = $this->actingAs($admin)->get('/operacion/auditoria');
        $page->assertStatus(200);
        $body = $page->response()->getBody();
        $this->assertStringContainsString('Limpiar logs', $body);
        $this->assertStringContainsString('data-audit-detail', $body);
        $this->assertStringContainsString('id="audit-detail-modal"', $body);

        $data = $this->actingAs($admin)->get('/operacion/auditoria/datos?draw=9&start=0&length=5');
        $data->assertStatus(200);
        $payload = json_decode($data->response()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Cliente #44', $payload['data'][0]['reference']);
        $this->assertStringContainsString('"campo": "teléfono"', $payload['data'][0]['details']);
    }

    public function testAdministratorCanClearEveryAuditEvent(): void
    {
        $admin = $this->account('administrador');
        $this->db->table('auditoria_eventos')->insertBatch([
            ['usuario_id' => $admin->id, 'registrado_en' => '2026-09-14 16:01:00', 'accion' => 'clientes.crear', 'referencia_textual' => 'cliente:1', 'detalle_sanitizado' => '{}'],
            ['usuario_id' => $admin->id, 'registrado_en' => '2026-09-14 16:02:00', 'accion' => 'clientes.editar', 'referencia_textual' => 'cliente:2', 'detalle_sanitizado' => '{}'],
        ]);

        $response = $this->withSession()->actingAs($admin)->post('/operacion/auditoria/limpiar', [
            csrf_token() => csrf_hash(),
        ]);

        $response->assertRedirectTo('/operacion/auditoria');
        $this->assertSame(0, $this->db->table('auditoria_eventos')->countAllResults());
    }

    public function testUserWithReadOnlyAuditAccessCannotClearEvents(): void
    {
        $cashier = $this->account('cajera');
        $cashier->addPermission('auditoria.ver');
        $this->db->table('auditoria_eventos')->insert([
            'usuario_id' => $cashier->id,
            'registrado_en' => '2026-09-14 16:03:00',
            'accion' => 'clientes.crear',
            'referencia_textual' => 'cliente:3',
            'detalle_sanitizado' => '{}',
        ]);

        $page = $this->actingAs($cashier)->get('/operacion/auditoria');
        $page->assertStatus(200);
        $this->assertStringNotContainsString('Limpiar logs', $page->response()->getBody());

        $response = $this->withSession()->actingAs($cashier)->post('/operacion/auditoria/limpiar', [
            csrf_token() => csrf_hash(),
        ]);
        $response->assertStatus(403);
        $this->assertSame(1, $this->db->table('auditoria_eventos')->countAllResults());
    }

    public function testAuditFallbackPaginationUsesFiveRowsPerPage(): void
    {
        $admin = $this->account('administrador');
        $rows = [];
        for ($number = 1; $number <= 12; $number++) {
            $rows[] = [
                'usuario_id' => $admin->id,
                'registrado_en' => sprintf('2026-09-14 13:%02d:00', $number),
                'accion' => 'pagos.registrar',
                'referencia_textual' => 'pago:' . $number,
                'detalle_sanitizado' => '{}',
            ];
        }
        $this->db->table('auditoria_eventos')->insertBatch($rows);

        $response = $this->actingAs($admin)->get('/operacion/auditoria?accion=pagos.registrar');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('Página <span class="font-data">1</span> de <span class="font-data">3</span>', $body);
        $this->assertStringContainsString('rel="next"', $body);
    }

    public function testStatementMakesEveryMovementAvailableToItsLocalDataTable(): void
    {
        $admin = $this->account('administrador');
        $this->db->table('clientes')->insert([
            'nombre' => 'Cliente con historial extenso',
            'activo' => 1,
            'version' => 1,
            'creado_en' => gmdate('Y-m-d H:i:s'),
        ]);
        $clientId = (int) $this->db->insertID();

        for ($number = 1; $number <= 30; $number++) {
            $this->db->table('operaciones')->insert([
                'clave_idempotencia' => 'statement-' . $number,
                'hash_solicitud' => str_repeat((string) ($number % 10), 64),
                'accion' => 'documentos.confirmar',
                'usuario_id' => $admin->id,
                'origen' => 'OPERATIVO',
                'efectiva_en' => '2026-09-14 10:00:00',
                'registrada_en' => '2026-09-14 10:00:00',
            ]);
            $operationId = (int) $this->db->insertID();
            $this->db->table('obligaciones')->insert([
                'cliente_id' => $clientId,
                'origen' => 'VENTA',
                'concepto' => 'Movimiento ' . $number,
                'fecha_origen' => sprintf('2026-08-%02d', $number),
                'importe_base' => '1.00',
                'operacion_creacion_id' => $operationId,
                'operacion_confirmacion_id' => $operationId,
            ]);
            $obligationId = (int) $this->db->insertID();
            $this->db->table('documentos_obligacion')->insert([
                'obligacion_id' => $obligationId,
                'tipo' => 'FACTURA',
                'numero_completo' => sprintf('FAC-TAB-%02d', $number),
                'numero_normalizado' => sprintf('FACTAB%02d', $number),
                'fecha_emision' => sprintf('2026-08-%02d', $number),
            ]);
        }

        $statement = (new StatementService())->forClient($clientId, []);

        $this->assertSame(30, $statement['total']);
        $this->assertCount(30, $statement['rows']);
    }

    public function testCatalogMakesEveryFilteredRowAvailableToItsLocalDataTable(): void
    {
        $admin = $this->account('administrador');
        $rows = [];
        for ($number = 1; $number <= 30; $number++) {
            $rows[] = [
                'codigo' => sprintf('TAB-%02d', $number),
                'tipo' => 'SERVICIO',
                'nombre' => sprintf('Servicio tabular %02d', $number),
                'precio_referencia' => '10.00',
                'activo' => 1,
                'version' => 1,
            ];
        }
        $this->db->table('items')->insertBatch($rows);

        $response = $this->actingAs($admin)->get('/catalogo?q=Servicio%20tabular');
        $response->assertStatus(200);
        $body = $response->response()->getBody();

        $this->assertStringContainsString('TAB-01', $body);
        $this->assertStringContainsString('TAB-30', $body);
    }
}
