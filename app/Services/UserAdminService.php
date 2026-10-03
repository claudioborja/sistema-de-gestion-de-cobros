<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ValidationException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

final class UserAdminService
{
    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        $user = model(UserModel::class)->findById($id);
        if (!$user) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'email' => (string) ($user->email ?? ''),
            'active' => (bool) $user->active,
            'group' => (string) (($user->getGroups() ?? [])[0] ?? 'cliente'),
            'permissions' => array_values($user->getPermissions() ?? []),
        ];
    }

    /** @param array<string, mixed> $input */
    public function save(int $actor, array $input, ?int $id = null): int
    {
        $creating = $id === null;
        Access::require($actor, $creating ? 'usuarios.crear' : 'usuarios.editar');
        $data = $this->validate($input, $creating);
        $db = db_connect();
        $users = model(UserModel::class);

        $duplicateUsername = $db->table('users')->where('username', $data['username']);
        if ($id) { $duplicateUsername->where('id !=', $id); }
        if ($duplicateUsername->countAllResults()) { throw new ValidationException(['username' => 'Este nombre de usuario ya existe.']); }
        $duplicateEmail = $db->table('auth_identities')->where('type', 'email_password')->where('secret', $data['email']);
        if ($id) { $duplicateEmail->where('user_id !=', $id); }
        if ($duplicateEmail->countAllResults()) { throw new ValidationException(['email' => 'Este correo ya está registrado.']); }

        $db->transException(true)->transBegin();
        try {
            if ($creating) {
                $id = (int) $users->insert(new User([
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'active' => 1,
                ]));
                $user = $users->findById($id);
            } else {
                $user = $users->findById($id);
                if (!$user) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
                $this->protectLastAdmin($user, $data['group']);
                $user->username = $data['username'];
                $user->email = $data['email'];
                $users->save($user);
            }
            $user->syncGroups($data['group']);
            Audit::record($actor, $creating ? 'usuarios.crear' : 'usuarios.editar', 'usuario:' . $id, ['grupo' => $data['group']]);
            if (!$db->transStatus() || !$db->transCommit()) { throw new \RuntimeException('No se pudo guardar el usuario.'); }
            return (int) $id;
        } catch (\Throwable $e) {
            $db->transRollback();
            if ($e instanceof ValidationException || $e instanceof \CodeIgniter\Exceptions\PageNotFoundException) { throw $e; }
            throw new ValidationException(['general' => 'No se pudo guardar el usuario. Revisa los datos e inténtalo de nuevo.']);
        }
    }

    public function setActive(int $actor, int $id, bool $active): void
    {
        Access::require($actor, 'usuarios.editar');
        $users = model(UserModel::class);
        $user = $users->findById($id);
        if (!$user) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        if (!$active) { $this->protectLastAdmin($user, null); }
        $users->update($id, ['active' => $active ? 1 : 0]);
        Audit::record($actor, 'usuarios.estado', 'usuario:' . $id, ['activo' => $active]);
    }

    /** @param mixed $input */
    public function savePermissions(int $actor, int $id, $input): void
    {
        Access::require($actor, 'usuarios.editar');
        $user = model(UserModel::class)->findById($id);
        if (!$user) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        $permissions = is_array($input) ? array_values(array_unique(array_filter($input, 'is_string'))) : [];
        $allowed = array_keys(config('AuthGroups')->permissions);
        foreach ($permissions as $permission) {
            if (!in_array($permission, $allowed, true)) { throw new ValidationException(['permissions' => 'La selección contiene un permiso no válido.']); }
        }
        $user->syncPermissions(...$permissions);
        Audit::record($actor, 'usuarios.permisos', 'usuario:' . $id, ['permisos' => $permissions]);
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    private function validate(array $input, bool $creating): array
    {
        $username = mb_substr(trim(is_string($input['username'] ?? null) ? $input['username'] : ''), 0, 30);
        $email = mb_strtolower(mb_substr(trim(is_string($input['email'] ?? null) ? $input['email'] : ''), 0, 254));
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $group = is_string($input['group'] ?? null) ? $input['group'] : '';
        $errors = [];
        if (preg_match('/^[a-zA-Z0-9._-]{3,30}$/D', $username) !== 1) { $errors['username'] = 'Usa entre 3 y 30 letras, números, puntos, guiones o guiones bajos.'; }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = 'Ingresa un correo electrónico válido.'; }
        if ($creating && mb_strlen($password) < 12) { $errors['password'] = 'La contraseña temporal debe tener al menos 12 caracteres.'; }
        if (!array_key_exists($group, config('AuthGroups')->groups)) { $errors['group'] = 'Selecciona una función válida.'; }
        if ($errors) { throw new ValidationException($errors); }
        return compact('username', 'email', 'password', 'group');
    }

    private function protectLastAdmin(User $user, ?string $newGroup): void
    {
        if (!$user->inGroup('administrador') || $newGroup === 'administrador') { return; }
        $count = db_connect()->table('auth_groups_users g')->join('users u', 'u.id = g.user_id')
            ->where('g.group', 'administrador')->where('u.active', 1)->where('u.deleted_at', null)->countAllResults();
        if ($count <= 1) { throw new ValidationException(['active' => 'Debe permanecer al menos un administrador activo.']); }
    }
}
