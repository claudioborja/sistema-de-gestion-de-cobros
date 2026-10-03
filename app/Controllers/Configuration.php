<?php

namespace App\Controllers;

use App\Domain\ValidationException;
use App\Services\ConfigurationService;
use App\Services\BankAccountService;

final class Configuration extends BaseController
{
    public function index(): string
    {
        $config = new ConfigurationService();

        return $this->renderPage('configuracion/index', [
            'title' => 'Configuración',
            'description' => 'Revisa los ajustes que controlan la operación, los cobros y la comunicación.',
            'pagePattern' => 'dashboard',
            'business' => $config->business(),
            'payments' => $config->payments(),
            'messages' => $config->messages(),
        ]);
    }

    public function business(): string
    {
        $config = new ConfigurationService();
        $business = $config->business();

        return $this->renderPage('configuracion/negocio', [
            'title' => 'Configuración del negocio',
            'description' => 'Ajusta datos fiscales, numeración y reglas operativas básicas.',
            'pagePattern' => 'detail',
            'business' => $business,
            'timezones' => ['America/Guayaquil', 'America/Chicago', 'America/Bogota', 'America/Lima', 'UTC'],
            'breadcrumbs' => [
                ['label' => 'Configuración', 'url' => site_url('configuracion')],
                ['label' => 'Negocio'],
            ],
        ]);
    }

    public function saveBusiness()
    {
        try {
            (new ConfigurationService())->saveBusiness((int) auth()->id(), $this->request->getPost());
            return redirect()->to(site_url('configuracion/negocio'))->with('message', 'Configuración guardada para el negocio.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url('configuracion/negocio'))->withInput()->with('errors', $e->errors);
        }
    }

    public function payments(?string $id = null): string
    {
        $config = new ConfigurationService();
        $payments = $config->payments();

        return $this->renderPage('configuracion/pagos', [
            'title' => 'Cuentas bancarias',
            'description' => 'Administra las cuentas receptoras que aparecen al registrar transferencias.',
            'pagePattern' => 'detail',
            'payments' => $payments,
            'accounts' => (new BankAccountService())->all((int) auth()->id()),
            'account' => $id === null ? null : (new BankAccountService())->find((int) auth()->id(), (int) $id),
            'breadcrumbs' => [
                ['label' => 'Configuración', 'url' => site_url('configuracion')],
                ['label' => 'Cuentas bancarias'],
            ],
        ]);
    }

    public function savePayments()
    {
        return $this->saveBankAccount();
    }

    public function editBankAccount(string $id): string
    {
        return $this->payments($id);
    }

    public function saveBankAccount(?string $id = null)
    {
        try {
            (new BankAccountService())->save((int) auth()->id(), $id === null ? null : (int) $id, $this->request->getPost());
            return redirect()->to(site_url('configuracion/pagos'))->with('message', 'Cuenta bancaria guardada. Ya está disponible según su estado.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url($id === null ? 'configuracion/pagos' : 'configuracion/cuentas-bancarias/' . $id . '/editar'))->withInput()->with('errors', $e->errors);
        }
    }

    public function bankAccountState(string $id)
    {
        try {
            (new BankAccountService())->setActive((int) auth()->id(), (int) $id, $this->request->getPost('activa'));
            return redirect()->to(site_url('configuracion/pagos'))->with('message', 'Estado actualizado. Los cobros anteriores se conservan.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url('configuracion/pagos'))->with('errors', $e->errors);
        }
    }

    public function messages(): string
    {
        $service = new ConfigurationService();
        $messages = $service->messages();
        $business = $service->business();
        $sampleClient = 'Cliente de ejemplo';
        $sampleInvoice = strtoupper($business['invoice_prefix']) . '-' . str_pad((string) $business['invoice_seed'], 5, '0', STR_PAD_LEFT);

        $tokens = [
            '{{empresa}}' => $business['business_name'],
            '{{cliente}}' => $sampleClient,
            '{{monto}}' => '$ 10,00',
            '{{fecha}}' => '11/09/2026',
            '{{fecha_vencimiento}}' => '18/09/2026',
            '{{comprobante}}' => $sampleInvoice,
        ];

        return $this->renderPage('configuracion/mensajes', [
            'title' => 'Plantillas de mensajes',
            'description' => 'Diseña correos de comprobante y recordatorios con variables de reemplazo.',
            'pagePattern' => 'detail',
            'messages' => $messages,
            'messagePreview' => [
                'invoice' => str_replace(array_keys($tokens), array_values($tokens), (string) $messages['invoice_body']),
                'reminder' => str_replace(array_keys($tokens), array_values($tokens), (string) $messages['reminder_body']),
            ],
            'breadcrumbs' => [
                ['label' => 'Configuración', 'url' => site_url('configuracion')],
                ['label' => 'Plantillas'],
            ],
        ]);
    }

    public function saveMessages()
    {
        try {
            (new ConfigurationService())->saveMessages((int) auth()->id(), $this->request->getPost());
            return redirect()->to(site_url('configuracion/mensajes'))->with('message', 'Plantillas guardadas para el negocio.');
        } catch (ValidationException $e) {
            return redirect()->to(site_url('configuracion/mensajes'))->withInput()->with('errors', $e->errors);
        }
    }
}
