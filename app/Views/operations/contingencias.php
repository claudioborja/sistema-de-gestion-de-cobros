<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', ['context' => 'Operación', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>
<nav class="mb-6 overflow-x-auto" aria-label="Navegación de operación"><div class="tabs tabs-box w-max min-w-full sm:min-w-0"><a class="tab" href="<?= site_url('operacion/estado') ?>"><i data-lucide="activity" class="icon" aria-hidden="true"></i>Estado</a><a class="tab tab-active" aria-current="page" href="<?= site_url('operacion/contingencias') ?>"><i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i>Contingencias</a><a class="tab" href="<?= site_url('operacion/auditoria') ?>"><i data-lucide="history" class="icon" aria-hidden="true"></i>Auditoría</a></div></nav>

<?php
$rows = $rows ?? [];
$stats = $stats ?? ['open' => 0, 'resolved' => 0];
$available = $available ?? false;
$contingencyClass = static fn (string $status): string => in_array($status, ['Resuelto', 'Cerrado'], true) ? 'badge-success badge-soft' : 'badge-warning badge-soft';
?>

<section class="grid gap-6 lg:grid-cols-2">
    <section class="card card-border bg-base-100">
        <div class="card-body">
            <div class="flex items-start gap-3"><i data-lucide="triangle-alert" class="icon mt-1 text-warning" aria-hidden="true"></i><div><h2 class="card-title">Registro no disponible</h2><p class="mt-2 text-base-content/70">La gestión de contingencias se habilitará cuando pueda conservar cada evento, responsable y resolución.</p></div></div>
            <div class="alert mt-5" role="status"><i data-lucide="info" class="icon" aria-hidden="true"></i><span>No se muestran casos de ejemplo ni se confirma información que no haya sido guardada.</span></div>
        </div>
    </section>

    <section class="card card-border bg-base-100"><div class="card-body">
        <h2 class="card-title">Resumen</h2>
        <div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-box border border-base-300 bg-base-200/50 p-4"><p class="text-sm text-base-content/70">Abiertas</p><p class="font-data mt-1 text-2xl font-semibold"><?= esc((string) $stats['open']) ?></p></div><div class="rounded-box border border-base-300 bg-base-200/50 p-4"><p class="text-sm text-base-content/70">Resueltas</p><p class="font-data mt-1 text-2xl font-semibold"><?= esc((string) $stats['resolved']) ?></p></div></div>
        <p class="mt-5 text-sm text-base-content/70">Últimos eventos registrados</p>
        <?php if (!$rows): ?>
            <?= view('partials/empty_state', ['icon' => 'circle-check', 'title' => 'Sin contingencias registradas', 'description' => 'Los eventos persistidos aparecerán aquí cuando el módulo esté disponible.'], ['saveData' => false]) ?>
        <?php else: ?>
            <div class="mt-2 hidden overflow-x-auto md:block"><table class="table"><caption class="sr-only">Contingencias registradas</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Referencia</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td class="font-data whitespace-nowrap"><?= esc($row['date']) ?></td><td class="font-data"><?= esc($row['reference']) ?></td><td><span class="badge <?= $contingencyClass((string) $row['status']) ?>"><?= esc($row['status']) ?></span></td><td><?= esc($row['notes']) ?></td></tr><?php endforeach ?></tbody></table></div>
            <ul class="mt-2 divide-y divide-base-300 md:hidden" aria-label="Contingencias registradas"><?php foreach ($rows as $row): ?><li class="record-card"><div class="min-w-0"><p class="font-data font-semibold"><?= esc($row['reference']) ?></p><p class="mt-1 text-sm"><?= esc($row['notes']) ?></p><p class="font-data mt-2 text-xs text-base-content/70"><?= esc($row['date']) ?></p></div><span class="badge <?= $contingencyClass((string) $row['status']) ?>"><?= esc($row['status']) ?></span></li><?php endforeach ?></ul>
        <?php endif ?>
    </div></section>
</section>

<?= $this->endSection() ?>
