<?php
declare(strict_types=1);
namespace App\Services;
use App\Domain\Amount;
use App\Domain\ValidationException;
final class CatalogService
{
    public function save(int $actor,array $input,?int $id=null): int
    {
        Access::require($actor,'catalogo.gestionar'); $errors=[];
        $code=is_string($input['codigo'] ?? null)?strtoupper(trim($input['codigo'])):'';
        $name=is_string($input['nombre'] ?? null)?trim($input['nombre']):'';
        $type=$input['tipo'] ?? '';
        if (!preg_match('/^[A-Z0-9_-]{2,40}$/D',$code)) {$errors['codigo']='Usa de 2 a 40 letras, números, guiones o guiones bajos.';}
        if (mb_strlen($name)<2 || mb_strlen($name)>160) {$errors['nombre']='Escribe entre 2 y 160 caracteres.';}
        if (!in_array($type,['PRODUCTO','SERVICIO'],true)) {$errors['tipo']='Selecciona producto o servicio.';}
        $price=null;
        try { if (($input['precio_referencia'] ?? '')!=='') {$price=Amount::price(is_string($input['precio_referencia'])?$input['precio_referencia']:'invalid');} }
        catch (\InvalidArgumentException $e) {$errors['precio_referencia']=$e->getMessage();}
        if ($errors) {throw new ValidationException($errors);}
        $db=db_connect(); $db->transException(true)->transBegin();
        try {
            $values=['codigo'=>$code,'nombre'=>$name,'tipo'=>$type,'precio_referencia'=>$price];
            if ($id) {
                $before=$db->query('SELECT * FROM items WHERE id=? FOR UPDATE',[$id])->getRowArray();
                if (!$before) {throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();}
                if ((string)$before['version']!==(string)($input['version'] ?? '')) {throw new ValidationException(['version'=>'El ítem cambió. Recarga la ficha.']);}
                $values['activo']=($input['activo'] ?? '')==='1'?1:0; $values['version']=(int)$before['version']+1;
                $db->table('items')->where('id',$id)->update($values);
            } else {$db->table('items')->insert($values);$id=(int)$db->insertID();}
            Audit::record($actor,'catalogo.guardar','item:'.$id,$values);
            if (!$db->transStatus() || !$db->transCommit()) {throw new \RuntimeException('No se pudo guardar el ítem.');}
            return $id;
        } catch (\Throwable $e) {$db->transRollback(); if ((int)$e->getCode()===1062) {throw new ValidationException(['codigo'=>'El código ya existe.']);} throw $e;}
    }
}
