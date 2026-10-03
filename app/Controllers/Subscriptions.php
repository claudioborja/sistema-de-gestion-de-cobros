<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Services\{Access,ClientService,SubscriptionService,FinancialDocumentService};
use App\Domain\ValidationException;
use CodeIgniter\HTTP\RedirectResponse;

final class Subscriptions extends BaseController
{
    public function index(?string $clientId=null): string
    {
        $q=$this->request->getGet('q'); $q=is_string($q) ? mb_substr(trim($q),0,100) : '';
        $page=max(1,min(100000,(int)$this->request->getGet('page')));
        return $this->renderPage('subscriptions/index', (new SubscriptionService())->listing($clientId ? (int)$clientId : null,$q,$page)+[
            'title'=>'Suscripciones','description'=>'Servicios recurrentes y sus períodos de cobro.',
            'client'=>$clientId ? (new ClientService())->find((int)$clientId) : null,'q'=>$q,'pagePattern'=>'list',
            'canManage'=>Access::can((int)auth()->id(),'contratos.gestionar')]);
    }
    public function select(): string|RedirectResponse
    {
        $id=$this->request->getGet('cliente_id');
        if (is_string($id) && ctype_digit($id) && (int)$id>0) {
            (new ClientService())->find((int)$id);
            return redirect()->to(site_url('clientes/'.(int)$id.'/suscripciones/nueva'));
        }
        return $this->renderPage('subscriptions/select',['title'=>'Nueva suscripción','description'=>'Selecciona al cliente que recibe el servicio.','pagePattern'=>'form']);
    }
    public function form(string $clientId): string
    {
        return $this->formPage((int)$clientId,[],[],[]);
    }
    public function preview(string $clientId): string
    {
        $input=$this->request->getPost(); $service=new SubscriptionService();
        try { $data=$service->validate($input); return $this->formPage((int)$clientId,$data,$service->preview($data),[]); }
        catch (ValidationException $e) { $this->response->setStatusCode(422); return $this->formPage((int)$clientId,$input,[],$e->errors); }
    }
    public function store(string $clientId): RedirectResponse|string
    {
        $input=$this->request->getPost();
        try {
            $service=new SubscriptionService(); $data=$service->validate($input);
            $preview=session('subscription_preview_'.(int)$clientId);
            if (!is_array($preview) || !hash_equals($preview['hash'],hash('sha256',json_encode($data)))) {
                throw new ValidationException(['preview'=>'Revisa la vista previa antes de activar.']);
            }
            $result=$service->create((int)auth()->id(),(int)$clientId,$data,$preview['key']);
            return redirect()->to(site_url('suscripciones/'.$result['id']))->with('message','Suscripción activada. Revisa los períodos y genera los cargos que correspondan.');
        } catch (ValidationException $e) { $this->response->setStatusCode(422); return $this->formPage((int)$clientId,$input,[],$e->errors); }
    }
    private function formPage(int $id,array $input,array $preview,array $errors): string
    {
        $client=(new ClientService())->find($id);
        if ($preview) {
            session()->set('subscription_preview_'.$id,['hash'=>hash('sha256',json_encode($input)),'key'=>bin2hex(random_bytes(16))]);
        }
        $input+=['item_id'=>'','start'=>SubscriptionService::today(),'end'=>'','months'=>'1','policy'=>'ANCLA','amount'=>'','billing'=>'ANTICIPADO','days'=>'0','acceptance'=>'0'];
        // Discard malformed arrays before any rendering.
        foreach ($input as $key=>$value) { if (!is_scalar($value)) { $input[$key]=''; } }
        return $this->renderPage('subscriptions/form',['title'=>'Nueva suscripción','description'=>'Define el servicio, calendario y precio de cada período.',
            'client'=>$client,'input'=>$input,'preview'=>$preview,'errors'=>$errors,'pagePattern'=>'form',
            'items'=>db_connect()->table('items')->where('tipo','SERVICIO')->where('activo',1)->orderBy('nombre')->get()->getResultArray()]);
    }
    public function detail(string $id): string
    {
        $service=new SubscriptionService(); $s=$service->find((int)$id); $today=SubscriptionService::today();
        $projection=$service->projection($s,$today);
        $generated=[]; $upcoming=[]; $future=0;
        foreach ($projection as $p) {
            if ($p['obligation_id']) {
                $p['balance']=(new FinancialDocumentService())->balance($p['obligation_id']);
                $p['status']=$p['balance']==='0.00' ? 'PAGADO' : ($p['due']<$today ? 'VENCIDO' : 'PENDIENTE');
                $generated[]=$p;
            } elseif ($p['start'] <= $today || $future++ < 3) { $upcoming[]=$p; }
        }
        return $this->renderPage('subscriptions/detail',['title'=>$s['servicio'],'description'=>'Suscripción #'.$s['id'].' · '.$s['cliente'],
            'subscription'=>$s,'client'=>(new ClientService())->find((int)$s['cliente_id']),'generated'=>$generated,'upcoming'=>$upcoming,
            'key'=>bin2hex(random_bytes(16)),'today'=>$today,'pagePattern'=>'detail',
            'canManage'=>Access::can((int)auth()->id(),'contratos.gestionar'),'canGenerate'=>Access::can((int)auth()->id(),'contratos.generar')]);
    }
    public function generate(string $id): RedirectResponse
    {
        return $this->act($id,fn($s)=>'Se generaron '.$s->generate((int)auth()->id(),(int)$id).' cargos. Los períodos ya generados no se duplican.');
    }
    public function state(string $id): RedirectResponse
    {
        return $this->act($id,function($s) use($id) { $s->state((int)auth()->id(),(int)$id,$this->post('type'),$this->post('date'),$this->post('reason'),$this->post('key')); return 'Cambio de estado registrado. Se conservaron los cargos anteriores.'; });
    }
    public function price(string $id): RedirectResponse
    {
        return $this->act($id,function($s) use($id) { $s->price((int)auth()->id(),(int)$id,$this->post('date'),$this->post('amount'),$this->post('reason'),$this->post('key')); return 'Nuevo precio programado. Los cargos anteriores conservan su importe.'; });
    }
    public function accept(string $id): RedirectResponse
    {
        return $this->act($id,function($s) use($id) { $choice=explode('|',$this->post('period'),2); $s->accept((int)auth()->id(),(int)$id,$choice[0],$this->post('evidence'),$this->post('key'),(int)($choice[1] ?? 0)); return 'Aceptación registrada para el período y precio indicados.'; });
    }
    private function act(string $id,callable $work): RedirectResponse
    {
        try { $message=$work(new SubscriptionService()); return redirect()->to(site_url('suscripciones/'.(int)$id))->with('message',$message); }
        catch (ValidationException $e) { return redirect()->to(site_url('suscripciones/'.(int)$id))->withInput()->with('subscription_form',basename(uri_string()))->with('error',implode(' ',$e->errors)); }
    }
    private function post(string $key): string
    {
        $v=$this->request->getPost($key); return is_string($v) ? trim($v) : '';
    }
}
