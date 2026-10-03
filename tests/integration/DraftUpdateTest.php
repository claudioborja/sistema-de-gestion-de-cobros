<?php

declare(strict_types=1);
namespace Tests\Integration;

use App\Domain\ValidationException;
use App\Services\FinancialDocumentService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class DraftUpdateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $namespace = null;
    protected $refresh = true;

    private function fixture(): array
    {
        $users = model(UserModel::class);
        $id = (int) $users->insert(new User(['username'=>'draft-admin', 'email'=>'draft-admin@example.test', 'password'=>'FixtureDraft2026!', 'active'=>1]));
        $users->findById($id)->syncGroups('administrador');
        $this->db->table('clientes')->insert(['nombre'=>'Cliente borrador', 'activo'=>1, 'version'=>1, 'creado_en'=>gmdate('Y-m-d H:i:s')]);
        $client = (int) $this->db->insertID();
        $service = new FinancialDocumentService();
        $draft = $service->createDraft($id, $client, $this->input(), 'draft-test-create');
        return [$service, $id, (int)$draft['obligation_id']];
    }

    private function input(string $number = 'DRAFT-001'): array
    {
        return ['type'=>'FACTURA', 'number'=>$number, 'issued_on'=>'2026-09-18', 'concept'=>'Servicios originales',
            'lines'=>[['description'=>'Servicio original', 'quantity'=>'1', 'amount'=>'100.00']]];
    }

    private function revision(FinancialDocumentService $service, int $id): string
    {
        return $service->find($id)['draft_revision'] ?? '';
    }

    public function testUpdateReplacesLinesPreservesClientAndAttachmentsAndAudits(): void
    {
        [$service,$actor,$id] = $this->fixture();
        $before = $service->find($id);
        $this->db->table('archivos_documento')->insert(['obligacion_id'=>$id,'nombre_original'=>'respaldo.pdf', 'ruta_relativa'=>'qa/respaldo.pdf', 'tipo_mime'=>'application/pdf','extension'=>'pdf','tamano_bytes'=>10,'hash_sha256'=>str_repeat('a',64),'cargado_por'=>$actor,'cargado_en'=>gmdate('Y-m-d H:i:s')]);
        $input=$this->input('DRAFT-002'); $input['concept']='Servicios corregidos'; $input['type']='NOTA_VENTA'; $input['issued_on']='2026-09-19';
        $input['lines']=[['description'=>'Primera línea','quantity'=>'2','amount'=>'25.10'],['description'=>'Segunda línea','quantity'=>'1','amount'=>'10.20']];
        $result=$service->updateDraft($actor,$id,$input,'draft-test-update',$this->revision($service,$id));
        $after=$service->find($id);
        $this->assertSame('35.30',$after['importe_base']);
        $this->assertSame('35.30',$result['amount']);
        $this->assertSame('Servicios corregidos',$after['concepto']);
        $this->assertSame('NOTA_VENTA',$after['tipo']);
        $this->assertSame('2026-09-19',$after['fecha_origen']);
        $this->assertSame($before['cliente_id'],$after['cliente_id']);
        $this->assertCount(2,$after['detalles']);
        $this->assertSame('0.00',$after['saldo']);
        $this->assertSame([],$after['cuotas']);
        $this->assertSame(1,$this->db->table('archivos_documento')->where('obligacion_id',$id)->countAllResults());
        $this->assertSame(1,$this->db->table('auditoria_eventos')->where('accion','documentos.borrador.editar')->countAllResults());
        $this->assertNotSame($before['draft_revision'] ?? '',$after['draft_revision']);
    }

    public function testReplayDoesNotWriteTwiceAndStaleFormCannotOverwrite(): void
    {
        [$service,$actor,$id]=$this->fixture(); $revision=$this->revision($service,$id);
        $input=$this->input('DRAFT-002');
        $result=$service->updateDraft($actor,$id,$input,'draft-test-update',$revision);
        $this->assertEquals($result,$service->updateDraft($actor,$id,$input,'draft-test-update',$revision));
        $this->assertSame(1,$this->db->table('auditoria_eventos')->where('accion','documentos.borrador.editar')->countAllResults());
        try { $service->updateDraft($actor,$id,$this->input('DRAFT-003'),'draft-test-stale',$revision); $this->fail('Stale edit accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('draft_revision',$e->errors); }
        $this->assertSame('DRAFT-002',$service->find($id)['numero_completo']);
    }

    public function testConfirmedDocumentCannotBeChanged(): void
    {
        [$service,$actor,$id]=$this->fixture(); $revision=$this->revision($service,$id);
        $service->confirm($actor,$id,['2026-10-18'],'draft-test-confirm');
        try { $service->updateDraft($actor,$id,$this->input('DRAFT-002'),'draft-test-update',$revision); $this->fail('Confirmed edit accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('document',$e->errors); }
        $this->assertSame('DRAFT-001',$service->find($id)['numero_completo']);
        $this->assertSame('100.00',$service->balance($id));
    }

    public function testDuplicateNumberRollsBackAllChanges(): void
    {
        [$service,$actor,$id]=$this->fixture(); $before=$service->find($id);
        $service->createDraft($actor,(int)$before['cliente_id'],$this->input('DRAFT-002'),'draft-test-other');
        $input=$this->input('DRAFT-002'); $input['concept']='No debe persistir'; $input['lines'][0]['amount']='40.00';
        try { $service->updateDraft($actor,$id,$input,'draft-test-update',$this->revision($service,$id)); $this->fail('Duplicate accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('number',$e->errors); }
        $this->assertSame($before,$service->find($id));
        $this->assertSame(0,$this->db->table('operaciones')->where('clave_idempotencia','draft-test-update')->countAllResults());
    }

    public function testInvalidInputDoesNotChangeDraft(): void
    {
        [$service,$actor,$id]=$this->fixture(); $before=$service->find($id);
        $input=$this->input(); $input['lines'][0]['amount']='-1';
        try { $service->updateDraft($actor,$id,$input,'draft-test-update',$this->revision($service,$id)); $this->fail('Invalid amount accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('lines.0.amount',$e->errors); }
        $this->assertSame($before,$service->find($id));
    }

    public function testActorWithoutPermissionCannotUpdate(): void
    {
        [$service,$actor,$id]=$this->fixture(); $before=$service->find($id);
        model(UserModel::class)->findById($actor)->syncGroups('cliente');
        $denied=false;
        try { $service->updateDraft($actor,$id,$this->input('DRAFT-002'),'draft-test-update',$this->revision($service,$id)); }
        catch (\RuntimeException $e) { $this->assertSame(403,$e->getCode()); $denied=true; }
        $this->assertTrue($denied);
        $this->assertSame($before,$service->find($id));
    }
}
