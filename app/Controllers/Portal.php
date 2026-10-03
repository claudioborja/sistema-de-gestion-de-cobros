<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\ValidationException;
use App\Services\CustomerPortalService;
use App\Services\PaymentService;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RedirectResponse;

final class Portal extends BaseController
{
    public function home(): RedirectResponse|string
    {
        if ((new CustomerPortalService())->hasLink((int) auth()->id())) {
            return redirect()->to(site_url('portal/reportes-pago'));
        }

        return $this->renderPortal('portal/unlinked', [
            'title' => 'Acceso pendiente',
            'description' => 'Tu cuenta necesita una invitación administrativa antes de usar el portal.',
            'pagePattern' => 'detail',
        ]);
    }

    public function invitation(string $token): string
    {
        return $this->renderPortal('portal/invitation', [
            'title' => 'Activar acceso',
            'description' => 'Confirma la vinculación antes de entrar al portal del cliente.',
            'invitation' => (new CustomerPortalService())->invitation((int) auth()->id(), $token),
            'token' => $token,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'pagePattern' => 'form',
        ]);
    }

    public function activate(string $token): RedirectResponse
    {
        try {
            (new CustomerPortalService())->activate(
                (int) auth()->id(),
                $token,
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('portal/reportes-pago'))
                ->with('message', 'Acceso activado. Ya puedes registrar y consultar tus comprobantes.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('portal/invitacion/' . $token))->with('errors', $error->errors);
        }
    }

    public function statements(): string
    {
        return $this->renderPortal('portal/statements', (new CustomerPortalService())->statements((int) auth()->id()) + [
            'title' => 'Mis comprobantes',
            'description' => 'Consulta los comprobantes que reportaste y el resultado de su revisión.',
            'pagePattern' => 'list',
        ]);
    }

    public function create(): string
    {
        $service = new CustomerPortalService();

        return $this->renderPortal('portal/create', [
            'title' => 'Registrar comprobante',
            'description' => 'Reporta una transferencia o depósito para que el negocio verifique el ingreso.',
            'client' => $service->linkedClient((int) auth()->id()),
            'bankAccounts' => (new PaymentService())->activeBankAccounts(),
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'pagePattern' => 'form',
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $result = (new CustomerPortalService())->createStatement(
                (int) auth()->id(),
                $this->request->getPost(),
                $this->request->getFile('attachment'),
                (string) $this->request->getPost('idempotency_key')
            );

            return redirect()->to(site_url('portal/reportes-pago/' . (int) $result['report_id']))
                ->with('message', 'Comprobante registrado. Su estado permanecerá pendiente hasta que el negocio lo revise.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('portal/reportes-pago/nuevo'))->withInput()->with('errors', $error->errors);
        }
    }

    public function detail(string $id): string
    {
        return $this->renderPortal('portal/detail', [
            'title' => 'Comprobante #' . (int) $id,
            'description' => 'Datos reportados e historial de revisión del comprobante.',
            'statement' => (new CustomerPortalService())->statement((int) auth()->id(), (int) $id),
            'pagePattern' => 'detail',
        ]);
    }

    public function download(string $id): DownloadResponse
    {
        $file = (new CustomerPortalService())->file((int) auth()->id(), (int) $id);
        $download = $this->response->download((string) $file['absolute_path'], null, true);
        if (!$download instanceof DownloadResponse) {
            throw new \RuntimeException('No se pudo preparar la descarga.');
        }

        return $download->setFileName((string) $file['nombre_original'])->noCache();
    }

    /** @param array<string, mixed> $data */
    private function renderPortal(string $view, array $data): string
    {
        service('renderer')->resetData();

        return view($view, $data + [
            'username' => auth()->user()->username ?? '',
            'portalLinked' => (new CustomerPortalService())->hasLink((int) auth()->id()),
        ], ['saveData' => true]);
    }
}
