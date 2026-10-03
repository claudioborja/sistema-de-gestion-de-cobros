<?php

namespace App\Controllers;

use App\Services\Access;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * Renderiza una vista interna con el contrato común de navegación.
     *
     * @param array<string, mixed> $data
     */
    protected function renderPage(string $view, array $data): string
    {
        $userId = (int) auth()->id();
        $definitions = [
            ['label' => 'Principal', 'icon' => 'layout-dashboard', 'items' => [
                ['url' => 'inicio', 'label' => 'Inicio', 'icon' => 'layout-dashboard', 'permission' => 'clientes.ver'],
            ]],
            ['label' => 'Gestión de clientes', 'icon' => 'users', 'items' => [
                ['url' => 'clientes', 'label' => 'Clientes', 'icon' => 'users', 'permission' => 'clientes.ver'],
                ['url' => 'suscripciones', 'label' => 'Suscripciones', 'icon' => 'calendar-clock', 'permission' => 'contratos.ver'],
                ['url' => 'catalogo', 'label' => 'Catálogo', 'icon' => 'package', 'permission' => 'catalogo.gestionar'],
            ]],
            ['label' => 'Caja y conciliación', 'icon' => 'landmark', 'items' => [
                ['url' => 'cajas', 'label' => 'Cajas', 'icon' => 'wallet', 'permission' => 'caja.gestionar'],
                ['url' => 'reportes-pago', 'label' => 'Comprobantes', 'icon' => 'scan-barcode', 'permission' => 'pagos.ver'],
                ['url' => 'mi-caja', 'label' => 'Mi caja', 'icon' => 'wallet-cards', 'permission' => 'caja.operar'],
            ]],
            ['label' => 'Análisis', 'icon' => 'chart-no-axes-combined', 'items' => [
                ['url' => 'reportes', 'label' => 'Reportes', 'icon' => 'chart-no-axes-combined', 'permission' => 'reportes.ver'],
            ]],
            ['label' => 'Administración', 'icon' => 'settings-2', 'items' => [
                ['url' => 'usuarios', 'label' => 'Usuarios', 'icon' => 'users-round', 'permission' => 'usuarios.ver'],
                ['url' => 'configuracion', 'label' => 'Configuración', 'icon' => 'settings-2', 'permission' => 'configuracion.gestionar'],
                ['url' => 'configuracion/pagos', 'label' => 'Cuentas bancarias', 'icon' => 'landmark', 'permission' => 'configuracion.gestionar'],
            ]],
            ['label' => 'Sistema', 'icon' => 'activity', 'items' => [
                ['url' => 'operacion/estado', 'label' => 'Estado', 'icon' => 'activity', 'permission' => 'operacion.ver'],
                ['url' => 'operacion/contingencias', 'label' => 'Contingencias', 'icon' => 'triangle-alert', 'permission' => 'operacion.ver'],
                ['url' => 'operacion/auditoria', 'label' => 'Auditoría', 'icon' => 'history', 'permission' => 'auditoria.ver'],
            ]],
        ];

        $navigation = [];
        foreach ($definitions as $group) {
            $items = array_values(array_filter(
                $group['items'],
                static fn (array $item): bool => Access::can($userId, $item['permission'])
            ));
            if ($items !== []) {
                $navigation[] = ['label' => $group['label'], 'icon' => $group['icon'], 'items' => $items];
            }
        }

        service('renderer')->resetData();

        return view($view, $data + [
            'navigation' => $navigation,
            'currentDate' => (new \DateTimeImmutable('now', new \DateTimeZone('America/Guayaquil')))->format('d/m/Y'),
            'username' => auth()->user()->username ?? '',
        ], ['saveData' => true]);
    }
}
