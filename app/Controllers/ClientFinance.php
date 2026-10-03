<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Access;
use App\Services\ClientService;
use App\Services\FinancialDocumentService;
use App\Services\PaymentService;
use App\Domain\ValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/** Financial navigation always resolves a real client on the server. */
final class ClientFinance extends BaseController
{
    private const SECTIONS = [
        'documentos' => ['Documentos', 'file-text'],
        'cartera' => ['Cartera', 'wallet-minimal'],
        'pagos' => ['Cobros', 'banknote'],
    ];

    public function select(string $section = 'pagos', string $action = ''): string|RedirectResponse
    {
        if (!isset(self::SECTIONS[$section]) || !in_array($action, ['', 'nuevo'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $id = $this->request->getGet('cliente_id');
        if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
            $client = (new ClientService())->find((int) $id);
            if ($action === 'nuevo' && !$client['activo']) {
                return redirect()->to(site_url($section . '/' . $action))->with('error', 'Selecciona un cliente activo para iniciar una operación.');
            }
            return redirect()->to(site_url('clientes/' . (int) $id . '/' . $section . ($action ? '/' . $action : '')));
        }
        return $this->renderPage('finance/select_client', [
            'title' => 'Seleccionar cliente',
            'description' => 'Busca al cliente para abrir ' . mb_strtolower(self::SECTIONS[$section][0]) . ' dentro de su expediente.',
            'section' => $section,
            'action' => $action,
            'activeOnly' => $action === 'nuevo',
            'pagePattern' => 'form',
        ]);
    }

    public function index(string $id, string $section): string
    {
        return $this->page($id, $section, false);
    }

    public function create(string $id, string $section): string|RedirectResponse
    {
        $client = (new ClientService())->find((int) $id);
        if (!$client['activo']) {
            return redirect()->to(site_url('clientes/' . (int) $id . '/' . $section))->with('error', 'El cliente está inactivo. Puedes consultar su historial, pero no iniciar operaciones.');
        }
        return $this->page($id, $section, true);
    }

    private function page(string $id, string $section, bool $create): string
    {
        if (!isset(self::SECTIONS[$section]) || ($create && $section === 'cartera')) {
            throw PageNotFoundException::forPageNotFound();
        }
        $client = (new ClientService())->find((int) $id);
        if ($section === 'documentos') {
            $documents = new FinancialDocumentService();
            return $this->renderPage('finance/client_documents', [
                'title' => $create ? 'Nuevo documento' : 'Documentos',
                'description' => 'Documentos financieros de ' . $client['nombre'] . '.',
                'client' => $client,
                'creating' => $create,
                'rows' => $create ? [] : $documents->listForClient((int) $id),
                'idempotencyKey' => bin2hex(random_bytes(16)),
                'pagePattern' => $create ? 'form' : 'list',
            ]);
        }
        if ($section === 'cartera') {
            return $this->renderPage('finance/client_portfolio', [
                'title' => 'Cartera',
                'description' => 'Saldos confirmados de ' . $client['nombre'] . '.',
                'client' => $client,
                'items' => (new FinancialDocumentService())->portfolioForClient((int) $id),
                'pagePattern' => 'list',
            ]);
        }
        if ($section === 'pagos') {
            $payments = new PaymentService();
            return $this->renderPage('finance/client_payments', [
                'title' => $create ? 'Registrar cobro' : 'Cobros',
                'description' => 'Cobros y aplicaciones de ' . $client['nombre'] . '.',
                'client' => $client,
                'creating' => $create,
                'rows' => $create ? [] : $payments->listForClient((int) $id),
                'installments' => $create ? $payments->openInstallmentsForClient((int) $id) : [],
                'bankAccounts' => $create ? $payments->activeBankAccounts() : [],
                'idempotencyKey' => bin2hex(random_bytes(16)),
                'pagePattern' => $create ? 'form' : 'list',
            ]);
        }
        return $this->renderPage('finance/client_workspace', [
            'title' => $create ? ($section === 'pagos' ? 'Registrar cobro' : 'Nuevo documento') : self::SECTIONS[$section][0],
            'description' => 'Operaciones de ' . $client['nombre'] . '.',
            'icon' => self::SECTIONS[$section][1],
            'client' => $client,
            'section' => $section,
            'creating' => $create,
            'canCreate' => Access::can((int) auth()->id(), $section === 'pagos' ? 'pagos.crear' : 'cartera.ver'),
            'pagePattern' => $create ? 'form' : 'list',
        ]);
    }

    public function storeDocument(string $id): RedirectResponse
    {
        $client = (new ClientService())->find((int) $id);
        $postedId = $this->request->getPost('cliente_id');
        if (!is_string($postedId) || $postedId !== (string) $client['id']) {
            return redirect()->to(site_url('clientes/' . (int) $id . '/documentos/nuevo'))
                ->withInput()->with('errors', ['client' => 'El cliente del formulario no coincide con el expediente.']);
        }
        $lines = $this->request->getPost('lines');
        $lines = is_array($lines) ? array_values(array_filter($lines, static function ($line): bool {
            return is_array($line) && trim((string) ($line['description'] ?? '') . (string) ($line['amount'] ?? '')) !== '';
        })) : [];
        try {
            $result = (new FinancialDocumentService())->createDraft((int) auth()->id(), (int) $id, [
                'type' => $this->request->getPost('type'),
                'number' => $this->request->getPost('number'),
                'issued_on' => $this->request->getPost('issued_on'),
                'concept' => $this->request->getPost('concept'),
                'lines' => $lines,
            ], (string) $this->request->getPost('idempotency_key'));

            return redirect()->to(site_url('documentos/' . (int) $result['obligation_id']))
                ->with('message', 'Borrador guardado. Revísalo antes de confirmar sus cuotas.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('clientes/' . (int) $id . '/documentos/nuevo'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function storePayment(string $id): RedirectResponse
    {
        $client = (new ClientService())->find((int) $id);
        $postedId = $this->request->getPost('cliente_id');
        if (!is_string($postedId) || $postedId !== (string) $client['id']) {
            return redirect()->to(site_url('clientes/' . (int) $id . '/pagos/nuevo'))
                ->withInput()->with('errors', ['client' => 'El cliente del formulario no coincide con el expediente.']);
        }
        $applications = $this->request->getPost('applications');
        $applications = is_array($applications) ? array_values(array_filter($applications, static function ($application): bool {
            return is_array($application) && trim((string) ($application['amount'] ?? '')) !== '';
        })) : [];
        try {
            $result = (new PaymentService())->confirmBankTransfer((int) auth()->id(), (int) $id, [
                'amount' => $this->request->getPost('amount'),
                'bank_account_id' => $this->request->getPost('bank_account_id'),
                'reference' => $this->request->getPost('reference'),
                'bank_date' => $this->request->getPost('bank_date'),
                'applications' => $applications,
            ], (string) $this->request->getPost('idempotency_key'));

            return redirect()->to(site_url('pagos/' . (int) $result['payment_id']))
                ->with('message', 'Cobro confirmado y aplicado a la cartera.');
        } catch (ValidationException $error) {
            return redirect()->to(site_url('clientes/' . (int) $id . '/pagos/nuevo'))
                ->withInput()->with('errors', $error->errors);
        }
    }

    public function unavailable(string $id, string $section): string
    {
        $client = (new ClientService())->find((int) $id);
        $postedId = $this->request->getPost('cliente_id');
        if (!is_string($postedId) || $postedId !== (string) $client['id']) {
            $this->response->setStatusCode(422);
            return $this->renderPage('finance/unavailable', ['title' => 'Revisa el cliente', 'description' => 'El cliente del formulario no coincide con el expediente. No se registró ninguna operación.', 'client' => $client]);
        }
        $this->response->setStatusCode(409);
        return $this->renderPage('finance/unavailable', ['title' => 'Operación no disponible', 'description' => 'El registro financiero aún no está habilitado. No se guardó ni confirmó ninguna operación.', 'client' => $client]);
    }
}
