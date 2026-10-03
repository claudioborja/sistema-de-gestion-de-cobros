<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserDirectoryService;
use App\Services\UserAdminService;
use App\Services\UserProfileService;
use App\Domain\ValidationException;

final class Accounts extends BaseController
{
    public function index(): string
    {
        return $this->renderPage('accounts/index', [
            'title' => 'Usuarios',
            'description' => 'Personas con acceso al sistema y su función asignada.',
            'pagePattern' => 'list',
            'users' => (new UserDirectoryService())->all(),
            'canCreate' => \App\Services\Access::can((int) auth()->id(), 'usuarios.crear'),
        ]);
    }

    public function form(?string $id = null): string
    {
        $account = $id ? (new UserAdminService())->find((int) $id) : [];
        return $this->renderPage('accounts/form', [
            'title' => $id ? 'Editar usuario' : 'Nuevo usuario',
            'description' => $id ? 'Actualiza la identidad y función asignada.' : 'Crea una cuenta individual con acceso controlado.',
            'pagePattern' => 'form',
            'account' => $account,
            'groups' => config('AuthGroups')->groups,
            'availablePermissions' => config('AuthGroups')->permissions,
            'breadcrumbs' => [
                ['label' => 'Usuarios', 'url' => site_url('usuarios')],
                ['label' => $id ? 'Editar usuario' : 'Nuevo usuario'],
            ],
        ]);
    }

    public function save(?string $id = null)
    {
        try {
            (new UserAdminService())->save((int) auth()->id(), $this->request->getPost(), $id ? (int) $id : null);
            return redirect()->to(site_url('usuarios'))->with('message', $id ? 'Usuario actualizado.' : 'Usuario creado.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url($id ? 'usuarios/' . $id : 'usuarios/nuevo'))->withInput()->with('errors', $e->errors);
        }
    }

    public function state(string $id)
    {
        try {
            (new UserAdminService())->setActive((int) auth()->id(), (int) $id, $this->request->getPost('active') === '1');
            return redirect()->to(site_url('usuarios/' . $id))->with('message', 'Estado del usuario actualizado.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url('usuarios/' . $id))->with('errors', $e->errors);
        }
    }

    public function permissions(string $id)
    {
        try {
            (new UserAdminService())->savePermissions((int) auth()->id(), (int) $id, $this->request->getPost('permissions'));
            return redirect()->to(site_url('usuarios/' . $id))->with('message', 'Permisos individuales actualizados.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url('usuarios/' . $id))->with('errors', $e->errors);
        }
    }

    public function mine(): string
    {
        return $this->renderPage('accounts/mine', [
            'title' => 'Mi cuenta',
            'description' => 'Identidad y permisos efectivos de tu sesión actual.',
            'pagePattern' => 'detail',
            'account' => (new UserDirectoryService())->mine((int) auth()->id()),
        ]);
    }

    public function updateMine()
    {
        try {
            (new UserProfileService())->updateIdentity((int) auth()->id(), $this->request->getPost());
            return redirect()->to(site_url('mi-cuenta'))->with('message', 'Datos de la cuenta actualizados.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url('mi-cuenta'))->withInput()->with('errors', $e->errors);
        }
    }

    public function password()
    {
        try {
            (new UserProfileService())->changePassword((int) auth()->id(), $this->request->getPost());
            return redirect()->to(site_url('mi-cuenta'))->with('message', 'Contraseña actualizada.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url('mi-cuenta'))->with('errors', $e->errors);
        }
    }
}
