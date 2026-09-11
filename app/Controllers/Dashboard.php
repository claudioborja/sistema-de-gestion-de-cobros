<?php
namespace App\Controllers;
final class Dashboard extends BaseController
{
    public function index(): string
    {
        $db=db_connect();
        return view('dashboard',['title'=>'Inicio','total'=>$db->table('clientes')->countAllResults(),'active'=>$db->table('clientes')->where('activo',1)->countAllResults(),'recent'=>$db->table('clientes')->orderBy('id','DESC')->limit(5)->get()->getResultArray()]);
    }
}
