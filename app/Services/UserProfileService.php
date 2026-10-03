<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ValidationException;
use CodeIgniter\Shield\Models\UserModel;

final class UserProfileService
{
    /** @param array<string, mixed> $input */
    public function updateIdentity(int $actor, array $input): void
    {
        $username = mb_substr(trim(is_string($input['username'] ?? null) ? $input['username'] : ''), 0, 30);
        $email = mb_strtolower(mb_substr(trim(is_string($input['email'] ?? null) ? $input['email'] : ''), 0, 254));
        $errors = [];
        if (preg_match('/^[a-zA-Z0-9._-]{3,30}$/D', $username) !== 1) { $errors['username'] = 'Usa entre 3 y 30 letras, números, puntos, guiones o guiones bajos.'; }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = 'Ingresa un correo electrónico válido.'; }
        $db = db_connect();
        if ($db->table('users')->where('username', $username)->where('id !=', $actor)->countAllResults()) { $errors['username'] = 'Este nombre de usuario ya existe.'; }
        if ($db->table('auth_identities')->where('type', 'email_password')->where('secret', $email)->where('user_id !=', $actor)->countAllResults()) { $errors['email'] = 'Este correo ya está registrado.'; }
        if ($errors) { throw new ValidationException($errors); }

        $users = model(UserModel::class);
        $user = $users->findById($actor);
        if (!$user) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        $db->transException(true)->transBegin();
        try {
            $user->username = $username;
            $user->email = $email;
            $users->save($user);
            Audit::record($actor, 'usuarios.perfil', 'usuario:' . $actor);
            if (!$db->transStatus() || !$db->transCommit()) { throw new \RuntimeException('No se pudo actualizar el perfil.'); }
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }

    /** @param array<string, mixed> $input */
    public function changePassword(int $actor, array $input): void
    {
        $current = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $confirmation = is_string($input['password_confirm'] ?? null) ? $input['password_confirm'] : '';
        $users = model(UserModel::class);
        $user = $users->findById($actor);
        if (!$user) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        $errors = [];
        if (!service('passwords')->verify($current, (string) $user->password_hash)) { $errors['current_password'] = 'La contraseña actual no coincide.'; }
        if (mb_strlen($password) < 12) { $errors['password'] = 'La nueva contraseña debe tener al menos 12 caracteres.'; }
        if ($password !== $confirmation) { $errors['password_confirm'] = 'Las contraseñas nuevas no coinciden.'; }
        if ($current !== '' && hash_equals($current, $password)) { $errors['password'] = 'La nueva contraseña debe ser diferente de la actual.'; }
        if ($errors) { throw new ValidationException($errors); }

        $db = db_connect();
        $db->transException(true)->transBegin();
        try {
            $user->password = $password;
            $users->save($user);
            Audit::record($actor, 'usuarios.contrasena', 'usuario:' . $actor);
            if (!$db->transStatus() || !$db->transCommit()) { throw new \RuntimeException('No se pudo cambiar la contraseña.'); }
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
}
