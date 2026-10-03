<?php
namespace App\Controllers;

use App\Services\DashboardService;

final class Dashboard extends BaseController
{
    public function index(): string
    {
        $overview = (new DashboardService())->overview((int) auth()->id());

        return $this->renderPage('dashboard', $overview + [
            'title' => 'Inicio',
            'description' => 'Revisa el estado de la operación y continúa las tareas principales.',
            'pagePattern' => 'dashboard',
        ]);
    }
}
