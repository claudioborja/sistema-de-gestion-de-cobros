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
        if (!auth()->loggedIn()) {return redirect()->to(site_url('login'));}
        if (!Access::can((int)auth()->id(),$arguments[0] ?? 'clientes.ver')) {
            return service('response')->setStatusCode(403)->setBody(view('errors/access',['title'=>'Acceso restringido']));
        }
    }
    public function after(RequestInterface $request,ResponseInterface $response,$arguments=null)
    {
        $response->setHeader('Cache-Control','no-store, private')->setHeader('X-Robots-Tag','noindex, nofollow');
    }
}
