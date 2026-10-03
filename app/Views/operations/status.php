<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', ['context' => 'Reportes y operación', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>

<nav class="mb-6 overflow-x-auto" aria-label="Navegación de operación">
    <div class="tabs tabs-box w-max min-w-full sm:min-w-0">
        <a class="tab tab-active" aria-current="page" href="<?= site_url('operacion/estado') ?>"><i data-lucide="activity" class="icon" aria-hidden="true"></i>Estado</a>
        <a class="tab" href="<?= site_url('operacion/contingencias') ?>"><i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i>Contingencias</a>
        <a class="tab" href="<?= site_url('operacion/auditoria') ?>"><i data-lucide="history" class="icon" aria-hidden="true"></i>Auditoría</a>
    </div>
</nav>

<?php
$overallLevel = $summary['error'] > 0 ? 'error' : ($summary['warning'] > 0 ? 'warning' : ($summary['unknown'] > 0 ? 'info' : 'success'));
$overallMessage = match ($overallLevel) {
    'error' => 'Hay componentes que requieren intervención.',
    'warning' => 'El sistema responde, pero hay comprobaciones que requieren atención.',
    'info' => 'Las funciones esenciales responden; algunos servicios todavía no están supervisados.',
    default => 'Todos los componentes supervisados están operativos.',
};
$visuals = [
    'success' => ['icon' => 'circle-check', 'iconClass' => 'text-success', 'badgeClass' => 'badge-success badge-soft'],
    'warning' => ['icon' => 'triangle-alert', 'iconClass' => 'text-warning', 'badgeClass' => 'badge-warning badge-soft'],
    'error' => ['icon' => 'circle-alert', 'iconClass' => 'text-error', 'badgeClass' => 'badge-error badge-soft'],
    'unknown' => ['icon' => 'info', 'iconClass' => 'text-base-content/60', 'badgeClass' => 'badge-ghost'],
];
?>

<div class="alert alert-<?= esc($overallLevel, 'attr') ?> alert-soft mb-6" role="status">
    <i data-lucide="<?= $overallLevel === 'error' ? 'circle-alert' : ($overallLevel === 'warning' ? 'triangle-alert' : 'info') ?>" class="icon shrink-0" aria-hidden="true"></i>
    <div>
        <p class="font-semibold"><?= esc($overallMessage) ?></p>
        <p class="mt-1 text-sm">Comprobado el <time datetime="<?= esc($checkedAtIso, 'attr') ?>" class="font-data"><?= esc($checkedAt) ?></time>.</p>
    </div>
</div>

<section class="card card-border bg-base-100" aria-labelledby="components-title">
    <div class="card-body p-0">
        <header class="section-heading flex-wrap">
            <div><h2 id="components-title" class="card-title">Componentes</h2><p class="mt-1 text-sm text-base-content/70">Cada estado proviene de una comprobación independiente ejecutada al cargar la página.</p></div>
            <div class="flex flex-wrap gap-2 text-sm" aria-label="Resumen del diagnóstico">
                <span class="badge badge-success badge-soft"><?= esc((string) $summary['success']) ?> operativos</span>
                <span class="badge badge-warning badge-soft"><?= esc((string) $summary['warning']) ?> con atención</span>
                <span class="badge badge-error badge-soft"><?= esc((string) $summary['error']) ?> con error</span>
                <span class="badge badge-ghost"><?= esc((string) $summary['unknown']) ?> no supervisados</span>
            </div>
        </header>
        <ul class="list" aria-label="Estado de componentes">
            <?php foreach ($checks as $check): $visual = $visuals[$check['level']] ?? $visuals['unknown']; ?>
            <li class="list-row items-center border-b border-base-300 last:border-b-0" data-health-check="<?= esc($check['key'], 'attr') ?>" data-health-level="<?= esc($check['level'], 'attr') ?>">
                <i data-lucide="<?= esc($visual['icon'], 'attr') ?>" class="icon <?= esc($visual['iconClass'], 'attr') ?>" aria-hidden="true"></i>
                <div class="list-col-grow min-w-0"><p class="font-semibold"><?= esc($check['component']) ?></p><p class="mt-1 text-sm text-base-content/70"><?= esc($check['detail']) ?></p></div>
                <span class="badge <?= esc($visual['badgeClass'], 'attr') ?>"><?= esc($check['state']) ?></span>
            </li>
            <?php endforeach ?>
        </ul>
    </div>
</section>

<p class="mt-4 text-sm text-base-content/70">El diagnóstico no envía correos, no ejecuta tareas programadas y no crea respaldos. Solo informa evidencia disponible.</p>

<?= $this->endSection() ?>
