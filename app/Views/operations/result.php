<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', ['context' => 'Operación', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>

<?php $status = $status ?? 'Pendiente'; ?>
<?php $statusClass = match (mb_strtolower((string) $status)) { 'completada', 'completado', 'finalizada', 'finalizado', 'exitosa', 'exitoso' => 'badge-success badge-soft', 'pendiente', 'en revisión' => 'badge-warning badge-soft', 'fallida', 'fallido', 'error' => 'badge-error badge-soft', default => 'badge-ghost' }; ?>
<nav class="mb-6 overflow-x-auto" aria-label="Navegación de operación"><div class="tabs tabs-box w-max min-w-full sm:min-w-0"><a class="tab" href="<?= site_url('operacion/estado') ?>"><i data-lucide="activity" class="icon" aria-hidden="true"></i>Estado</a><a class="tab" href="<?= site_url('operacion/contingencias') ?>"><i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i>Contingencias</a><a class="tab" href="<?= site_url('operacion/auditoria') ?>"><i data-lucide="history" class="icon" aria-hidden="true"></i>Auditoría</a></div></nav>
<section class="card card-border bg-base-100" aria-labelledby="operation-result-title">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="eyebrow mb-2">Confirmación recuperable</p><h2 id="operation-result-title" class="card-title">Operación registrada</h2><p class="mt-1 text-sm text-base-content/70">Referencia <span class="font-data break-all"><?= esc($operationKey) ?></span></p></div><span class="badge <?= $statusClass ?>"><?= esc($status) ?></span></div>
        <div class="alert alert-success mt-5" role="status"><i data-lucide="circle-check" class="icon shrink-0" aria-hidden="true"></i><span>El resultado quedó disponible aunque la conexión se haya interrumpido.</span></div>
        <?php if (! empty($retry)): ?>
            <p class="mt-4 text-sm text-base-content/70">Si necesitas revisar el origen o repetir la acción, vuelve al módulo de pagos.</p>
            <div class="card-actions mt-4"><a class="btn btn-primary" href="<?= site_url('pagos') ?>"><i data-lucide="receipt" class="icon" aria-hidden="true"></i>Ir a pagos</a><a class="btn" href="<?= site_url('operacion/auditoria') ?>">Ver auditoría</a></div>
        <?php endif ?>
    </div>
</section>

<?= $this->endSection() ?>
