<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Models\UserModel;

final class Recovery extends BaseController
{
    private const string RESET_IDENTITY_TYPE = 'password_reset';
    private const int RESET_TOKEN_TTL_SECONDS = 3600;

    public function form()
    {
        if (auth()->loggedIn()) {
            return redirect()->to('/');
        }

        return view('auth/recover_access', [
            'title' => 'Recuperar acceso',
        ]);
    }

    public function request()
    {
        if (auth()->loggedIn()) {
            return redirect()->to('/');
        }

        $email = mb_strtolower(trim((string) $this->request->getPost('email')));
        $errors = [];

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Ingresa un correo electrónico válido.';
        }

        if ($errors) {
            return redirect()->to(site_url('recuperar-acceso'))->withInput()->with('errors', $errors);
        }

        $userModel = model(UserModel::class);
        $user = $userModel->findByCredentials(['email' => $email]);

        if ($user !== null) {
            $token = $this->createResetToken((int) $user->id);
            $link = site_url('restablecer-acceso/' . $token);
            $this->sendResetLink($email, $link);
        }

        return redirect()->to(site_url('recuperar-acceso'))->with('message', 'Si el correo existe, te enviamos un enlace para restablecer la contraseña.');
    }

    public function reset(string $token)
    {
        if (auth()->loggedIn()) {
            return redirect()->to('/');
        }

        $identity = $this->getResetIdentity($token);
        if ($identity === null) {
            return redirect()->to(site_url('recuperar-acceso'))->with('error', 'El enlace de recuperación no es válido o ya venció.');
        }

        return view('auth/reset_access', [
            'title' => 'Restablecer contraseña',
            'token' => $token,
        ]);
    }

    public function restore(string $token)
    {
        if (auth()->loggedIn()) {
            return redirect()->to('/');
        }

        $identity = $this->getResetIdentity($token);
        if ($identity === null) {
            return redirect()->to(site_url('recuperar-acceso'))->with('error', 'El enlace de recuperación no es válido o ya venció.');
        }

        $password = is_string($this->request->getPost('password')) ? $this->request->getPost('password') : '';
        $passwordConfirm = is_string($this->request->getPost('password_confirm')) ? $this->request->getPost('password_confirm') : '';
        $errors = [];

        if (mb_strlen($password) < 12) {
            $errors['password'] = 'La contraseña debe tener al menos 12 caracteres.';
        }
        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'La confirmación no coincide.';
        }

        if ($errors) {
            return redirect()->to(site_url('restablecer-acceso/' . $token))->withInput()->with('errors', $errors);
        }

        $userModel = model(UserModel::class);
        $user = $userModel->findById((int) $identity->user_id);
        if (! $user) {
            return redirect()->to(site_url('recuperar-acceso'))->with('error', 'No se encontró una cuenta válida para recuperar.');
        }

        $user->setPassword($password);
        $userModel->save($user);

        model(UserIdentityModel::class)->delete((int) $identity->id);

        return redirect()->to(site_url('login'))->with('message', 'Tu contraseña se actualizó correctamente.');
    }

    private function createResetToken(int $userId): string
    {
        $identityModel = model(UserIdentityModel::class);
        $identityModel->where('user_id', $userId)
            ->where('type', self::RESET_IDENTITY_TYPE)
            ->delete();

        $token = bin2hex(random_bytes(32));

        $identityModel->insert([
            'user_id' => $userId,
            'type' => self::RESET_IDENTITY_TYPE,
            'name' => 'password_reset',
            'secret' => hash('sha256', $token),
            'secret2' => null,
            'expires' => Time::now()->addSeconds(self::RESET_TOKEN_TTL_SECONDS),
            'extra' => null,
            'force_reset' => false,
        ]);

        return $token;
    }

    private function getResetIdentity(string $token): ?UserIdentity
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
            return null;
        }

        $identityModel = model(UserIdentityModel::class);
        $row = $identityModel
            ->where('type', self::RESET_IDENTITY_TYPE)
            ->where('secret', hash('sha256', $token))
            ->where('expires >=', Time::now())
            ->first();

        if (! $row instanceof UserIdentity) {
            return null;
        }

        return $row;
    }

    private function sendResetLink(string $to, string $link): void
    {
        $emailConfig = config('Email');
        if (empty($emailConfig->fromEmail)) {
            return;
        }

        try {
            $email = service('email');
            $email->setTo($to);
            $email->setFrom($emailConfig->fromEmail, $emailConfig->fromName ?: 'Cobros');
            $email->setSubject('Recupera tu contraseña');
            $email->setMessage("Haz clic en el enlace para recuperar tu acceso: {$link}");
            $email->send();
        } catch (\Throwable $e) {
            log_message('warning', 'No se pudo enviar correo de recuperación: ' . $e->getMessage());
        }
    }
}
