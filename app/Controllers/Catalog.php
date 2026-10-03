<?php
namespace App\Controllers;
use App\Services\CatalogService;
use App\Domain\ValidationException;
final class Catalog extends BaseController
{
    public function index(): string
    {
        $q=mb_substr(is_string($this->request->getGet('q'))?$this->request->getGet('q'):'',0,100);
        $query=db_connect()->table('items');
        if ($q!=='') {$query->groupStart()->like('nombre',$q)->orLike('codigo',$q)->groupEnd();}
        $total=$query->countAllResults(false);
        return $this->renderPage('catalog/index', [
            'title' => 'Catálogo',
            'description' => 'Conceptos y precios de referencia para tu negocio.',
            'pagePattern' => 'list',
            'rows' => $query->orderBy('nombre')->orderBy('id')->get()->getResultArray(),
            'total' => $total,
            'q' => $q,
        ]);
    }
    public function form(?string $id=null): string
    {
        $item=$id?db_connect()->table('items')->where('id',(int)$id)->get()->getRowArray():[];
        if ($id && !$item) {throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
        return $this->renderPage('catalog/form', [
            'title' => $id ? 'Editar ítem' : 'Nuevo ítem',
            'description' => 'Define el concepto y su precio de referencia comercial.',
            'pagePattern' => 'form',
            'item' => $item,
            'breadcrumbs' => [
                ['label' => 'Catálogo', 'url' => site_url('catalogo')],
                ['label' => $id ? 'Editar ítem' : 'Nuevo ítem'],
            ],
        ]);
    }
    public function save(?string $id=null)
    {
        try {(new CatalogService())->save((int)auth()->id(),$this->request->getPost(),$id?(int)$id:null);return redirect()->to(site_url('catalogo'))->with('message','Ítem guardado.');}
        catch (ValidationException $e) {return redirect()->to(site_url($id?'catalogo/'.$id:'catalogo/nuevo'))->withInput()->with('errors',$e->errors);}
    }
    public function detail(string $id): string
    {
        $item = db_connect()->table('items')->where('id', (int) $id)->get()->getRowArray();
        if (!$item) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }

        return $this->renderPage('catalog/detail', [
            'title' => 'Detalle de ítem',
            'description' => 'Ficha comercial del producto o servicio seleccionado.',
            'pagePattern' => 'detail',
            'item' => $item,
            'breadcrumbs' => [
                ['label' => 'Catálogo', 'url' => site_url('catalogo')],
                ['label' => 'Detalle'],
            ],
        ]);
    }
}
