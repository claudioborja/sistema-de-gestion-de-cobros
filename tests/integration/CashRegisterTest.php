<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Domain\ValidationException;
use App\Services\CashRegisterService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class CashRegisterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;
    protected $refresh = true;

    private function actor(string $group = 'administrador'): int
    {
        $users = model(UserModel::class);
        $suffix = bin2hex(random_bytes(4));
        $id = (int) $users->insert(new User(['username'=>'cash-'.$suffix,'email'=>'cash-'.$suffix.'@example.test','password'=>'FixtureCash2026!','active'=>1]));
        $users->findById($id)->syncGroups($group);
        return $id;
    }

    private function input(): array
    {
        return ['codigo'=>' caja-01 ', 'nombre'=>' Mostrador principal ', 'activa'=>'1'];
    }

    public function testCreatesAndListsPhysicalRegisterWithoutFinancialEffects(): void
    {
        $service = new CashRegisterService(); $actor = $this->actor();
        $id = $service->save($actor, $this->input());
        $this->assertGreaterThan(0, $id);
        $row = $service->find($actor, $id);
        $this->assertSame('CAJA-01', $row['codigo']);
        $this->assertSame('Mostrador principal', $row['nombre']);
        $this->assertSame(1, (int) $row['activa']);
        $this->assertSame(1, (int) $row['version']);
        $this->assertCount(1, $service->all($actor));
        $this->assertSame(0, $this->db->table('pagos')->countAllResults());
        $this->assertSame(0, $this->db->table('obligaciones')->countAllResults());
        $this->assertSame(1, $this->db->table('auditoria_eventos')->where('accion','cajas.crear')->countAllResults());
    }

    public function testUpdatesDeactivatesAndReactivatesWithoutDeleting(): void
    {
        $service = new CashRegisterService(); $actor = $this->actor();
        $id = $service->save($actor, $this->input());
        $this->assertGreaterThan(0, $id);
        $input = ['codigo'=>'CAJA-01','nombre'=>'Mostrador actualizado','activa'=>'0','version'=>'1'];
        $this->assertSame($id, $service->save($actor, $input, $id));
        $row = $service->find($actor, $id);
        $this->assertSame('Mostrador actualizado', $row['nombre']);
        $this->assertSame(0, (int)$row['activa']);
        $this->assertSame(2, (int)$row['version']);
        $input['activa']='1'; $input['version']='2';
        $service->save($actor, $input, $id);
        $this->assertSame(1, (int)$service->find($actor,$id)['activa']);
        $this->assertCount(1,$service->all($actor));
    }

    public function testRejectsDuplicateCodeAndKeepsOriginal(): void
    {
        $service = new CashRegisterService(); $actor = $this->actor();
        $id = $service->save($actor, $this->input());
        $this->assertGreaterThan(0, $id);
        try { $service->save($actor,['codigo'=>'CAJA-01','nombre'=>'Duplicada','activa'=>'1']); $this->fail('Duplicate accepted'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('codigo',$error->errors); }
        $this->assertCount(1,$service->all($actor));
        $this->assertSame('Mostrador principal',$service->find($actor,$id)['nombre']);
        $this->assertSame(1,$this->db->table('auditoria_eventos')->where('accion','cajas.crear')->countAllResults());
    }

    public function testRejectsStaleEditWithoutOverwritingOrAuditing(): void
    {
        $service = new CashRegisterService(); $actor = $this->actor();
        $id = $service->save($actor, $this->input());
        $this->assertGreaterThan(0, $id);
        $input=['codigo'=>'CAJA-01','nombre'=>'Cambio primero','activa'=>'1','version'=>'1'];
        $service->save($actor,$input,$id);
        $input['nombre']='Cambio obsoleto';
        try { $service->save($actor,$input,$id); $this->fail('Stale edit accepted'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('version',$error->errors); }
        $this->assertSame('Cambio primero',$service->find($actor,$id)['nombre']);
        $this->assertSame(1,$this->db->table('auditoria_eventos')->where('accion','cajas.editar')->countAllResults());
    }

    public function testRejectsInvalidFields(): void
    {
        $service = new CashRegisterService(); $actor = $this->actor();
        try { $service->save($actor,['codigo'=>'../','nombre'=>'','activa'=>'otro']); $this->fail('Invalid input accepted'); }
        catch (ValidationException $error) {
            foreach (['codigo','nombre','activa'] as $field) $this->assertArrayHasKey($field,$error->errors);
        }
        $this->assertSame([], $service->all($actor));
    }

    public function testCashierCannotManageRegisters(): void
    {
        $service = new CashRegisterService(); $actor = $this->actor('cajera');
        foreach (['save','all'] as $method) {
            $denied=false;
            try { $method==='save' ? $service->save($actor,$this->input()) : $service->all($actor); }
            catch (\RuntimeException $error) { $this->assertSame(403,$error->getCode()); $denied=true; }
            $this->assertTrue($denied);
        }
    }
}
