<?php
namespace Tests\Integration;
use App\Services\SubscriptionService;
use App\Services\FinancialDocumentService;
use App\Domain\ValidationException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
final class SubscriptionTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticationTesting;
    protected $namespace = null;
    protected $refresh = true;
    private function fixture(array $overrides = []): array
    {
        $users = new UserModel();
        $id = $users->insert(new User(['username'=>'sub-admin', 'email'=>'sub@example.test','password'=>'Fixture-Subs-2026!', 'active'=>1]));
        $user = $users->findById($id); $user->syncGroups('administrador');
        $this->db->table('clientes')->insert(['nombre'=>'Cliente Moodle','activo'=>1,'version'=>1,'creado_en'=>gmdate('Y-m-d H:i:s')]);
        $client = (int)$this->db->insertID();
        $this->db->table('items')->insert(['codigo'=>'MOODLE','nombre'=>'Acceso a Moodle','tipo'=>'SERVICIO','activo'=>1,'version'=>1]);
        $input = array_replace(['item_id'=>(string)$this->db->insertID(), 'start'=>'2026-01-31', 'end'=>'', 'months'=>'1','policy'=>'ANCLA','amount'=>'20.00','billing'=>'ANTICIPADO','days'=>'5','acceptance'=>'0'], $overrides);
        return [$user,$client,$input];
    }
    public function testRepeatedGenerationCreatesOneDebtPerPeriod(): void
    {
        [$u,$c,$in]=$this->fixture(); $s=new SubscriptionService();
        $id=$s->create((int)$u->id,$c,$in,'subscription-create-1')['id'];
        $this->assertSame($id,$s->create((int)$u->id,$c,$in,'subscription-create-1')['id']);
        $this->assertSame(3,$s->generate((int)$u->id,$id,'2026-03-31'));
        $this->assertSame(0,$s->generate((int)$u->id,$id,'2026-03-31'));
        $p=$this->db->table('periodos_contrato')->where('contrato_id',$id)->orderBy('periodo_desde')->get()->getResultArray();
        $this->assertCount(3,$p);
        $this->assertSame('20.00',(new FinancialDocumentService())->balance((int)$p[0]['obligacion_id']));
        $this->assertSame('2026-02-05',$this->db->table('cuota_versiones')->orderBy('cuota_id')->get()->getRow()->fecha_vencimiento);
        $this->actingAs($u)->get('/suscripciones/'.$id)->assertStatus(200);
        $this->actingAs($u)->get('/clientes/'.$c.'/suscripciones')->assertStatus(200);
    }
    public function testPauseAndResumeSkipWholePeriodsAndPreserveDebt(): void
    {
        [$u,$c,$in]=$this->fixture(); $s=new SubscriptionService(); $a=(int)$u->id;
        $id=$s->create($a,$c,$in,'subscription-create-2')['id'];
        $s->state($a,$id,'PAUSA','2026-02-28','Solicitud del cliente','subscription-pause-2');
        $s->state($a,$id,'REACTIVACION','2026-03-31','Retorno del cliente','subscription-resume-2');
        $this->assertSame(2,$s->generate($a,$id,'2026-03-31'));
        $this->assertSame(['2026-01-31','2026-03-31'],array_column($this->db->table('periodos_contrato')->orderBy('periodo_desde')->get()->getResultArray(),'periodo_desde'));
    }
    public function testArrearsGenerateCompletedPeriodAtCancellationBoundary(): void
    {
        [$u,$c,$in]=$this->fixture(['billing'=>'VENCIDO']); $s=new SubscriptionService(); $a=(int)$u->id;
        $id=$s->create($a,$c,$in,'subscription-create-3')['id'];
        $s->state($a,$id,'CANCELACION','2026-02-28','Finaliza el servicio','subscription-cancel-3');
        $this->assertSame(1,$s->generate($a,$id,'2026-04-30'));
    }
    public function testRejectsMidPeriodPause(): void
    {
        [$u,$c,$in]=$this->fixture(); $s=new SubscriptionService();
        $id=$s->create((int)$u->id,$c,$in,'subscription-create-4')['id'];
        $this->expectException(ValidationException::class);
        $s->state((int)$u->id,$id,'PAUSA','2026-02-15','Solicitud del cliente','subscription-pause-4');
    }
    public function testPriceChangePreservesGeneratedAmounts(): void
    {
        [$u,$c,$in]=$this->fixture(['start'=>'2030-01-01']); $s=new SubscriptionService(); $a=(int)$u->id;
        $id=$s->create($a,$c,$in,'subscription-create-5')['id'];
        $s->generate($a,$id,'2030-01-01');
        $s->price($a,$id,'2030-02-01','25.00','Nueva tarifa pactada','subscription-price-5');
        $s->generate($a,$id,'2030-02-01');
        $this->assertSame(['20.00','25.00'],array_column($this->db->table('obligaciones')->orderBy('id')->get()->getResultArray(),'importe_base'));
    }
    public function testAcceptanceGatesDebtAndRequiresEvidence(): void
    {
        [$u,$c,$in]=$this->fixture(['start'=>'2030-01-01','acceptance'=>'1']); $s=new SubscriptionService(); $a=(int)$u->id;
        $id=$s->create($a,$c,$in,'subscription-create-6')['id'];
        $this->assertSame(0,$s->generate($a,$id,'2030-01-01'));
        $s->accept($a,$id,'2030-01-01','Correo del cliente del 17 de septiembre, contrato firmado','subscription-accept-6');
        $this->assertSame(1,$s->generate($a,$id,'2030-01-01'));
    }
    public function testExternalUserCannotViewOrGenerateSubscriptions(): void
    {
        [$u,$c,$in]=$this->fixture(); $s=new SubscriptionService();
        $id=$s->create((int)$u->id,$c,$in,'subscription-create-7')['id'];
        $u->syncGroups('cliente');
        $this->actingAs($u)->get('/suscripciones/'.$id)->assertStatus(403);
        $this->expectException(\RuntimeException::class);
        $s->generate((int)$u->id,$id,'2026-03-31');
    }

    public function testPaymentClearsOnlyItsOwnMonth(): void
    {
        [$u,$c,$in]=$this->fixture(); $s=new SubscriptionService(); $a=(int)$u->id;
        $id=$s->create($a,$c,$in,'subscription-payment-create')['id'];
        $s->generate($a,$id,'2026-02-28');
        $payments=new \App\Services\PaymentService();
        $dues=$payments->openInstallmentsForClient($c);
        $this->assertCount(2,$dues);
        $this->db->table('cuentas_bancarias')->insert(['institucion'=>'Banco de pruebas','numero_cuenta'=>'SUB-TEST','alias'=>'Pruebas','activa'=>1]);
        $payments->confirmBankTransfer($a,$c,['amount'=>'20.00','bank_account_id'=>(string)$this->db->insertID(),
            'reference'=>'SUB-PAY-1','bank_date'=>'2026-02-01',
            'applications'=>[['installment_id'=>(string)$dues[0]['id'],'amount'=>'20.00']]],'subscription-payment-confirm');
        $remaining=$payments->openInstallmentsForClient($c);
        $this->assertCount(1,$remaining);
        $this->assertSame($dues[1]['id'],$remaining[0]['id']);
        $this->assertSame('0.00',(new FinancialDocumentService())->balance((int)$dues[0]['obligacion_id']));
    }
    public function testSimultaneousWorkersGenerateExactlyOnePeriod(): void
    {
        [$u,$c,$in]=$this->fixture(); $s=new SubscriptionService();
        $id=$s->create((int)$u->id,$c,$in,'subscription-concurrent-create')['id'];
        $processes=[];
        for ($i=0;$i<2;$i++) {
            $pipes=[];
            $process=proc_open([PHP_BINARY,ROOTPATH.'tests/_support/subscription_worker.php',(string)$u->id,(string)$id,'2026-01-31'],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOTPATH);
            $this->assertIsResource($process); fclose($pipes[0]);
            $processes[]=[$process,$pipes];
        }
        $total=0;
        foreach ($processes as [$process,$pipes]) {
            $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $this->assertSame(0,proc_close($process),$error); $total+=(int)$output;
        }
        $this->assertSame(1,$total);
        $this->assertSame(1,$this->db->table('periodos_contrato')->where('contrato_id',$id)->countAllResults());
        $this->assertSame(1,$this->db->table('obligaciones')->where('cliente_id',$c)->countAllResults());
    }
    public function testPreviewActivationAndCsrfThroughHttp(): void
    {
        [$u,$c,$in]=$this->fixture();
        $this->actingAs($u)->get('/clientes/'.$c.'/suscripciones/nueva')->assertStatus(200);
        $this->withSession()->actingAs($u)->post('/clientes/'.$c.'/suscripciones',$in+[csrf_token()=>csrf_hash()])->assertStatus(422);
        $preview=$this->withSession()->actingAs($u)->post('/clientes/'.$c.'/suscripciones/vista-previa',$in+[csrf_token()=>csrf_hash()]);
        $preview->assertStatus(200);
        $created=$this->withSession()->actingAs($u)->post('/clientes/'.$c.'/suscripciones',$in+[csrf_token()=>csrf_hash()]);
        $created->assertRedirect();
        $id=(int)$this->db->table('contratos')->get()->getRow()->id;
        $this->assertSame(0,$this->db->table('obligaciones')->countAllResults());
        $this->withSession()->actingAs($u)->post('/suscripciones/'.$id.'/generar',[csrf_token()=>csrf_hash()])->assertRedirect();
        $this->assertGreaterThan(0,$this->db->table('periodos_contrato')->countAllResults());
        try {
            $this->actingAs($u)->post('/suscripciones/'.$id.'/generar',[]);
            $this->fail('Una escritura sin CSRF fue aceptada.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException $e) {
            $this->assertStringContainsString('not allowed',$e->getMessage());
        }
    }
    public function testLateAcceptanceCreatesNoDebt(): void
    {
        [$u,$c,$in]=$this->fixture(['acceptance'=>'1']); $s=new SubscriptionService();
        $id=$s->create((int)$u->id,$c,$in,'subscription-late-create')['id'];
        try { $s->accept((int)$u->id,$id,'2026-01-31','Correo recibido tarde','subscription-late-accept'); $this->fail('Aceptación tardía admitida'); }
        catch (ValidationException) { $this->assertSame(0,$s->generate((int)$u->id,$id,'2026-02-28')); }
        $this->assertSame(0,$this->db->table('renovacion_decisiones')->countAllResults());
    }
    public function testGeneratedPeriodCannotBePausedOrRepriced(): void
    {
        [$u,$c,$in]=$this->fixture(['start'=>'2030-01-01']); $s=new SubscriptionService(); $a=(int)$u->id;
        $id=$s->create($a,$c,$in,'subscription-immutable-create')['id']; $s->generate($a,$id,'2030-02-01');
        try { $s->price($a,$id,'2030-02-01','40.00','Tarifa no autorizada','subscription-immutable-price'); $this->fail('Precio histórico modificado'); }
        catch (ValidationException) { $this->assertSame(1,$this->db->table('contrato_condiciones')->countAllResults()); }
        try { $s->state($a,$id,'PAUSA','2030-02-01','Pausa no autorizada','subscription-immutable-pause'); $this->fail('Período cobrado pausado'); }
        catch (ValidationException) { $this->assertSame(0,$this->db->table('contrato_eventos')->countAllResults()); }
    }

    public function testAcceptanceRejectsPriceChangedSinceThePageWasOpened(): void
    {
        [$u,$c,$in]=$this->fixture(['start'=>'2030-01-01','acceptance'=>'1']); $s=new SubscriptionService(); $a=(int)$u->id;
        $id=$s->create($a,$c,$in,'subscription-stale-create')['id'];
        $s->price($a,$id,'2030-02-01','30.00','Nuevo precio acordado','subscription-stale-price');
        $this->expectException(ValidationException::class);
        $s->accept($a,$id,'2030-02-01','Cliente aceptó veinte dólares','subscription-stale-accept',1);
    }
}
