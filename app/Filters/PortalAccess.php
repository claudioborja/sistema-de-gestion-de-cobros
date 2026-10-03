<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\ConfigurationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class PortalAccess implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!auth()->loggedIn()) {
            session()->setTempdata('beforeLoginUrl', current_url(), 300);
            return redirect()->to(site_url('login'));
        }

        $user = auth()->user();
        if (!$user || !$user->active || $user->isBanned() || !$user->inGroup('cliente')) {
            return service('response')->setStatusCode(403)->setBody(view('errors/access', ['title' => 'Acceso restringido']));
        }

        $business = (new ConfigurationService())->business();
        if (empty($business['portal_enabled'])) {
            return service('response')->setStatusCode(503)->setBody(view('errors/access', [
                'title' => 'Portal no disponible',
                'message' => 'El portal del cliente todavía no está habilitado por el negocio.',
            ]));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
