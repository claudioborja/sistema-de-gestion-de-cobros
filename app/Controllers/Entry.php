<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Access;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class Entry extends BaseController
{
    public function index(): RedirectResponse|ResponseInterface
    {
        if (!auth()->loggedIn()) {
            return redirect()->to(site_url('login'));
        }
        $user = auth()->user();
        if ($user && $user->active && !$user->isBanned() && $user->inGroup('cliente')) {
            return redirect()->to(site_url('portal'));
        }
        if (Access::can((int) auth()->id(), 'clientes.ver')) {
            return redirect()->to(site_url('inicio'));
        }

        return service('response')->setStatusCode(403)->setBody(view('errors/access', ['title' => 'Acceso restringido']));
    }
}
