<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Access;
use App\Services\OperationsService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

final class Operations extends BaseController
{
    public function audit(): string
    {
        $audit = (new OperationsService())->audit([
            'q' => $this->request->getGet('q'),
            'accion' => $this->request->getGet('accion'),
            'page' => $this->request->getGet('page'),
        ]);
        return $this->renderPage('operations/audit', $audit + [
            'title' => 'Auditoría',
            'description' => 'Trazabilidad reciente de las acciones administrativas.',
            'pagePattern' => 'list',
            'canClearAudit' => Access::can((int) auth()->id(), 'auditoria.limpiar'),
        ]);
    }

    public function auditData(): ResponseInterface
    {
        return $this->response->setJSON((new OperationsService())->auditDatatable($this->request->getGet()));
    }

    public function clearAudit(): ResponseInterface
    {
        $deleted = (new OperationsService())->clearAudit();
        $message = $deleted === 0
            ? 'No había eventos de auditoría para eliminar.'
            : sprintf('Se eliminaron %d eventos de auditoría.', $deleted);

        return redirect()->to(site_url('operacion/auditoria'))->with('message', $message);
    }

    public function status(): string
    {
        $health = (new OperationsService())->status();
        return $this->renderPage('operations/status', $health + [
            'title' => 'Estado operativo',
            'description' => 'Diagnóstico observado de servicios y dependencias esenciales.',
            'pagePattern' => 'detail',
        ]);
    }

    public function contingencias(): string
    {
        return $this->renderPage('operations/contingencias', [
            'title' => 'Contingencias',
            'description' => 'Registro y trazabilidad de eventos externos o divergentes.',
            'pagePattern' => 'list',
            'rows' => [],
            'stats' => ['open' => 0, 'resolved' => 0],
            'available' => false,
        ]);
    }

    public function saveContingencia(): ResponseInterface
    {
        return $this->response->setStatusCode(409)->setBody($this->contingencias());
    }

    public function result(string $key): string
    {
        // Nunca se confirma una operación sin resolver una clave persistida y su propietario.
        throw PageNotFoundException::forPageNotFound();
    }
}
