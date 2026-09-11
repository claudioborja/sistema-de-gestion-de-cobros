<?php
declare(strict_types=1);
namespace App\Services;
use App\Domain\ClientInput;
use App\Domain\ValidationException;
final class ClientService
{
    public function find(int $id): array
    {
        $db=db_connect();
        $row=$db->table('clientes')->where('id',$id)->get()->getRowArray();
        if (!$row) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        $identity=$db->table('cliente_identificaciones')->where('cliente_id',$id)->get()->getRowArray();
        $row += ['identificacion'=>$identity['numero_normalizado'] ?? '', 'tipo'=>$identity['tipo'] ?? 'CEDULA','pais'=>$identity['pais_emisor'] ?? 'EC','email'=>'','telefono'=>''];
        foreach ($db->table('cliente_contactos')->where('cliente_id',$id)->get()->getResultArray() as $contact) {
            if (in_array($contact['canal'],['email','telefono'],true)) { $row[$contact['canal']]=$contact['valor']; }
        }
        return $row;
    }
    public function save(int $actor, array $input, ?int $id=null): int
    {
        Access::require($actor,$id ? 'clientes.editar':'clientes.crear');
        $data=ClientInput::validate($input); $db=db_connect();
        $db->transException(true)->transBegin();
        try {
            $before=null;
            if ($id) {
                $db->query('SELECT id FROM clientes WHERE id=? FOR UPDATE',[$id]);
                $before=$this->find($id);
                if ((string)$before['version'] !== (string)($input['version'] ?? '')) { throw new ValidationException(['version'=>'Otro usuario modificó este cliente. Recarga la ficha.']); }
            }
            if ($data['identificacion'] !== '') {
                $query=$db->table('cliente_identificaciones')->where(['pais_emisor'=>$data['pais'],'tipo'=>$data['tipo'],'numero_normalizado'=>$data['identificacion']]);
                if ($id) { $query->where('cliente_id !=',$id); }
                if ($query->countAllResults()) { throw new ValidationException(['identificacion'=>'Esta identificación ya pertenece a otro cliente.']); }
            }
            $values=['nombre'=>$data['nombre'],'direccion'=>$data['direccion'] ?: null];
            if ($id) {
                $values['version']=(int)$before['version']+1;
                $db->table('clientes')->where('id',$id)->update($values);
                $db->table('cliente_identificaciones')->where('cliente_id',$id)->delete();
                $db->table('cliente_contactos')->where('cliente_id',$id)->whereIn('canal',['email','telefono'])->delete();
            } else {
                $db->table('clientes')->insert($values+['creado_en'=>gmdate('Y-m-d H:i:s')]); $id=(int)$db->insertID();
            }
            if ($data['identificacion'] !== '') { $db->table('cliente_identificaciones')->insert(['cliente_id'=>$id,'pais_emisor'=>$data['pais'],'tipo'=>$data['tipo'],'numero_normalizado'=>$data['identificacion']]); }
            foreach (['email','telefono'] as $channel) { if ($data[$channel] !== '') { $db->table('cliente_contactos')->insert(['cliente_id'=>$id,'canal'=>$channel,'valor'=>$data[$channel]]); } }
            Audit::record($actor,$before ? 'clientes.editar':'clientes.crear','cliente:'.$id,['antes'=>$before ? array_intersect_key($before,$data):null,'despues'=>$data]);
            if (!$db->transStatus() || !$db->transCommit()) { throw new \RuntimeException('No se pudo guardar el cliente.'); }
            return $id;
        } catch (\Throwable $e) {
            $db->transRollback();
            if ((int)$e->getCode()===1062) { throw new ValidationException(['identificacion'=>'Esta identificación ya está registrada.']); }
            throw $e;
        }
    }
    public function setActive(int $actor,int $id,bool $active): void
    {
        Access::require($actor,'clientes.desactivar'); $db=db_connect(); $db->transException(true)->transBegin();
        try {
            $db->query('SELECT id FROM clientes WHERE id=? FOR UPDATE',[$id]); $this->find($id);
            $db->table('clientes')->where('id',$id)->set('activo',(int)$active)->set('version','version+1',false)->update();
            Audit::record($actor,'clientes.estado','cliente:'.$id,['activo'=>$active]);
            if (!$db->transStatus() || !$db->transCommit()) { throw new \RuntimeException('No se pudo cambiar el estado.'); }
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
}
