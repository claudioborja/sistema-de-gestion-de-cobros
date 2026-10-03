<?php

declare(strict_types=1);

namespace App\Controllers;

final class Settings extends BaseController
{
    public function business(): string
    {
        return $this->renderPage('settings/business', [
            'title' => 'Configuración del negocio',
            'description' => 'Parámetros generales para facturación y operación.',
            'pagePattern' => 'form',
            'settings' => [
                'business_name' => old('business_name', 'CREDIAZ S.A.'),
                'currency' => old('currency', 'USD'),
                'timezone' => old('timezone', 'America/Guayaquil'),
                'invoice_prefix' => old('invoice_prefix', 'FAC'),
                'due_days_default' => old('due_days_default', '30'),
            ],
        ]);
    }

    public function saveBusiness(): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to(site_url('configuracion/negocio'))->with('message', 'Configuración empresarial guardada (modo provisional).');
    }

    public function payments(): string
    {
        return $this->renderPage('settings/payments', [
            'title' => 'Medios y cuentas receptoras',
            'description' => 'Configura canales de cobro sin publicar credenciales sensibles.',
            'pagePattern' => 'form',
            'settings' => [
                'cash_box' => old('cash_box', 'Caja principal'),
                'bank_account' => old('bank_account', 'EC-PANAMA-012345'),
                'wallet_id' => old('wallet_id', 'WALLET-001'),
                'allowed_channels' => old('allowed_channels', 'Efectivo, Transferencia, Tarjeta'),
                'min_payment' => old('min_payment', '5.00'),
            ],
        ]);
    }

    public function savePayments(): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to(site_url('configuracion/pagos'))->with('message', 'Canales de pago actualizados (sin persistencia real aún).');
    }

    public function messages(): string
    {
        return $this->renderPage('settings/messages', [
            'title' => 'Plantillas de mensajes',
            'description' => 'Diseña mensajes internos del flujo de aviso y cobranza.',
            'pagePattern' => 'form',
            'settings' => [
                'welcome_text' => old('welcome_text', 'Gracias por pagar a tiempo.'),
                'reminder_text' => old('reminder_text', 'Tienes una deuda pendiente.'),
                'overdue_text' => old('overdue_text', 'Este aviso requiere gestión inmediata.'),
                'signature' => old('signature', 'Equipo Cobros'),
            ],
        ]);
    }

    public function saveMessages(): \CodeIgniter\HTTP\RedirectResponse
    {
        return redirect()->to(site_url('configuracion/mensajes'))->with('message', 'Plantillas guardadas (modo plantilla visual).');
    }
}
