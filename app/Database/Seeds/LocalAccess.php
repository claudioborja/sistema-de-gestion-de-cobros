<?php
declare(strict_types=1);
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
final class LocalAccess extends Seeder
{
    public function run(): void
    {
        if (ENVIRONMENT!=='development' || $this->db->database!=='cobros_dev') {throw new \RuntimeException('Solo se permite en cobros_dev, entorno development.');}
        $users=new UserModel();
        if ($users->where('username','admin.local')->first()) {echo "El administrador local ya existe; no se modificó su contraseña.\n";return;}
        $password=bin2hex(random_bytes(18));
        $this->db->transException(true)->transBegin();
        try {
            $id=$users->insert(new User(['username'=>'admin.local','email'=>'admin@cobros.test','password'=>$password,'active'=>1]));
            if (!$id) {throw new \RuntimeException('No se pudo crear el usuario.');}
            $users->findById($id)->syncGroups('administrador');
            \App\Services\Audit::record((int)$id,'usuarios.alta_local','usuario:'.$id);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {throw new \RuntimeException('No se pudo confirmar el alta.');}
        } catch (\Throwable $e) {$this->db->transRollback();throw $e;}
        $file=WRITEPATH.'local-access.json';
        $old=umask(0077);
        file_put_contents($file,json_encode(['email'=>'admin@cobros.test','password'=>$password],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        umask($old);
        echo "Administrador local creado. Acceso privado en writable/local-access.json.\n";
    }
}
