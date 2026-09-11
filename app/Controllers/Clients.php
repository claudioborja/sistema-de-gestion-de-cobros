<?php
namespace App\Controllers;
use App\Domain\ValidationException;
use App\Services\ClientService;
final class Clients extends BaseController
{
    public function index(): string
    {
        $q=mb_substr(is_string($this->request->getGet('q'))?$this->request->getGet('q'):'',0,100);
        $page=max(1,min(100000,(int)$this->request->getGet('page')));
        $query=db_connect()->table('clientes c');
        if ($q!=='') {$query->groupStart()->like('c.nombre',$q)->orWhereIn('c.id',db_connect()->table('cliente_identificaciones')->select('cliente_id')->like('numero_normalizado',$q))->groupEnd();}
        $status=$this->request->getGet('estado') ?? '';
        if (in_array($status,['0','1'],true)) {$query->where('c.activo',(int)$status);}
        $total=$query->countAllResults(false); $page=min($page,max(1,(int)ceil($total/25)));
        $rows=$query->select('c.*')->select('(SELECT numero_normalizado FROM cliente_identificaciones WHERE cliente_id=c.id ORDER BY tipo LIMIT 1) AS identificacion',false)->orderBy('c.nombre')->orderBy('c.id')->limit(25,($page-1)*25)->get()->getResultArray();
        return view('clients/index',['title'=>'Clientes','rows'=>$rows,'total'=>$total,'page'=>$page,'q'=>$q,'status'=>$status]);
    }
    public function form(?string $id=null): string
    {
        return view('clients/form',['title'=>$id?'Editar cliente':'Nuevo cliente','client'=>$id?(new ClientService())->find((int)$id):[]]);
    }
    public function save(?string $id=null)
    {
        try {
            (new ClientService())->save((int)auth()->id(),$this->request->getPost(),$id?(int)$id:null);
            return redirect()->to(site_url('clientes'))->with('message',$id?'Cliente actualizado.':'Cliente registrado.');
        } catch (ValidationException $e) {return redirect()->to(site_url($id?'clientes/'.$id:'clientes/nuevo'))->withInput()->with('errors',$e->errors);}
    }
    public function state(string $id)
    {
        (new ClientService())->setActive((int)auth()->id(),(int)$id,$this->request->getPost('activo')==='1');
        return redirect()->to(site_url('clientes'))->with('message','Estado actualizado. El historial se conserva.');
    }
}
