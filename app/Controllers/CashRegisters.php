<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\ValidationException;
use App\Services\CashRegisterService;
use CodeIgniter\HTTP\RedirectResponse;

final class CashRegisters extends BaseController
{
    public function index(): string
    {
        return $this->renderPage('cash_registers/index', [
            'title' => 'Cajas',
            'description' => 'Registro de los puntos físicos de cobro.',
            'pagePattern' => 'list',
            'rows' => (new CashRegisterService())->all((int) auth()->id()),
        ]);
    }

    public function form(?string $id = null): string
    {
        $register = $id === null ? [] : (new CashRegisterService())->find((int) auth()->id(), (int) $id);
        $title = $id === null ? 'Nueva caja' : 'Editar caja';

        return $this->renderPage('cash_registers/form', [
            'title' => $title,
            'description' => 'Identifica la caja física y su disponibilidad.',
            'pagePattern' => 'form',
            'register' => $register,
            'breadcrumbs' => [
                ['label' => 'Cajas', 'url' => site_url('cajas')],
                ['label' => $title],
            ],
        ]);
    }

    public function save(?string $id = null): RedirectResponse
    {
        try {
            (new CashRegisterService())->save((int) auth()->id(), [
                'codigo' => $this->request->getPost('codigo'),
                'nombre' => $this->request->getPost('nombre'),
                'activa' => $this->request->getPost('activa'),
                'version' => $this->request->getPost('version'),
            ], $id === null ? null : (int) $id);

            return redirect()->to(site_url('cajas'))->with('message', 'Caja guardada.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url($id === null ? 'cajas/nueva' : 'cajas/' . (int) $id . '/editar'))
                ->withInput()->with('errors', $error->errors);
        }
    }
}
