<?php
namespace App\Filters;
use App\Services\Access;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
final class InternalAccess implements FilterInterface
{
    public function before(RequestInterface $request,$arguments=null)
    {
        if (!auth()->loggedIn()) {
            if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return service('response')->setStatusCode(401)->setJSON(['error' => 'La sesión venció.']);
            }
            return redirect()->to(site_url('login'));
        }
        foreach ($arguments ?: ['clientes.ver'] as $permission) {
            if (!Access::can((int)auth()->id(),$permission)) {
                return service('response')->setStatusCode(403)->setBody(view('errors/access',['title'=>'Acceso restringido']));
            }
        }
    }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null)
    {
        $response->setHeader('Cache-Control','no-store, private')->setHeader('X-Robots-Tag','noindex, nofollow');
    }
}
