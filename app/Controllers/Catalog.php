<?php
namespace App\Controllers;
use App\Services\CatalogService;
use App\Domain\ValidationException;
final class Catalog extends BaseController
{
    public function index(): string
    {
        $q=mb_substr(is_string($this->request->getGet('q'))?$this->request->getGet('q'):'',0,100);
        $page=max(1,min(100000,(int)$this->request->getGet('page')));
        $query=db_connect()->table('items');
        if ($q!=='') {$query->groupStart()->like('nombre',$q)->orLike('codigo',$q)->groupEnd();}
        $total=$query->countAllResults(false);$page=min($page,max(1,(int)ceil($total/25)));
        return view('catalog/index',['title'=>'Catálogo','rows'=>$query->orderBy('nombre')->orderBy('id')->limit(25,($page-1)*25)->get()->getResultArray(),'total'=>$total,'page'=>$page,'q'=>$q]);
    }
    public function form(?string $id=null): string
    {
        $item=$id?db_connect()->table('items')->where('id',(int)$id)->get()->getRowArray():[];
        if ($id && !$item) {throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
        return view('catalog/form',['title'=>$id?'Editar ítem':'Nuevo ítem','item'=>$item]);
    }
    public function save(?string $id=null)
    {
        try {(new CatalogService())->save((int)auth()->id(),$this->request->getPost(),$id?(int)$id:null);return redirect()->to(site_url('catalogo'))->with('message','Ítem guardado.');}
        catch (ValidationException $e) {return redirect()->to(site_url($id?'catalogo/'.$id:'catalogo/nuevo'))->withInput()->with('errors',$e->errors);}
    }
}
