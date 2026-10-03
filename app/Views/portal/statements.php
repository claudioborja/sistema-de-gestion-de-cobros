<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php
$statusClass = static fn (string $status): string => match ($status) {
    'Aprobado', 'Verificado' => 'badge-success badge-soft',
    'Rechazado' => 'badge-error badge-soft',
    'En nueva revisión' => 'badge-info badge-soft',
    default => 'badge-warning badge-soft',
};
?>

<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'primaryAction' => ['label' => 'Registrar comprobante', 'url' => site_url('portal/reportes-pago/nuevo'), 'icon' => 'upload'],
], ['saveData' => false]) ?>

<section class="stats stats-vertical w-full border border-base-300 bg-base-100 sm:stats-horizontal" aria-label="Resumen de mis comprobantes">
    <div class="stat"><div class="stat-title">Pendientes</div><div class="stat-value font-data text-3xl"><?= (int) $totals['pending'] ?></div><div class="stat-desc">En espera o nueva revisión</div></div>
    <div class="stat"><div class="stat-title">Verificados</div><div class="stat-value font-data text-3xl"><?= (int) $totals['reviewed'] ?></div><div class="stat-desc">Ingreso revisado</div></div>
    <div class="stat"><div class="stat-title">Rechazados</div><div class="stat-value font-data text-3xl"><?= (int) $totals['rejected'] ?></div><div class="stat-desc">Requieren revisar el motivo</div></div>
</section>

<section class="card card-border mt-6 bg-base-100" aria-labelledby="my-statements-title">
    <div class="card-body p-0">
        <header class="section-heading"><div><h2 id="my-statements-title" class="card-title"><?= esc($client['nombre']) ?></h2><p class="mt-1 text-sm text-base-content/70">Solo aparecen comprobantes reportados desde las cuentas vinculadas a este cliente.</p></div></header>
        <?php if (!$rows): ?>
            <?= view('partials/empty_state', ['icon' => 'receipt-text', 'title' => 'Todavía no has reportado comprobantes', 'description' => 'Registra una transferencia o depósito para iniciar su revisión.', 'action' => ['label' => 'Registrar comprobante', 'url' => site_url('portal/reportes-pago/nuevo')]], ['saveData' => false]) ?>
        <?php else: ?>
        <div class="hidden overflow-x-auto md:block"><table class="table"><caption class="sr-only">Comprobantes reportados por mi cuenta</caption><thead><tr><th scope="col">Comprobante</th><th scope="col">Cuenta</th><th scope="col">Fecha</th><th scope="col" class="text-right">Monto</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><th scope="row" class="font-data">#<?= (int) $row['id'] ?></th><td><?= esc($row['institucion'] . ' · ' . $row['alias']) ?><div class="font-data text-xs text-base-content/60"><?= esc($row['referencia_reportada']) ?></div></td><td class="font-data"><?= esc($row['fecha_local']) ?></td><td class="font-data text-right">$<?= esc($row['monto']) ?></td><td><span class="badge <?= esc($statusClass((string) $row['estado']), 'attr') ?>"><?= esc($row['estado']) ?></span></td><td><a class="btn btn-sm" href="<?= site_url('portal/reportes-pago/' . (int) $row['id']) ?>">Ver revisión</a></td></tr><?php endforeach ?>
        </tbody></table></div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Mis comprobantes">
        <?php foreach ($rows as $row): ?><li class="p-5"><div class="flex items-start justify-between gap-3"><div><p class="font-data font-semibold">Comprobante #<?= (int) $row['id'] ?></p><p class="mt-1 text-sm text-base-content/70"><?= esc($row['institucion'] . ' · ' . $row['alias']) ?></p></div><span class="badge <?= esc($statusClass((string) $row['estado']), 'attr') ?>"><?= esc($row['estado']) ?></span></div><dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-base-content/60">Fecha</dt><dd class="font-data mt-1"><?= esc($row['fecha_local']) ?></dd></div><div class="text-right"><dt class="text-base-content/60">Monto</dt><dd class="font-data mt-1 font-semibold">$<?= esc($row['monto']) ?></dd></div></dl><a class="btn mt-4 w-full" href="<?= site_url('portal/reportes-pago/' . (int) $row['id']) ?>">Ver revisión</a></li><?php endforeach ?>
        </ul>
        <?php endif ?>
    </div>
</section>

<?= $this->endSection() ?>
