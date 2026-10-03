<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php
$statusClass = match ($statement['estado']) {
    'Aprobado', 'Verificado' => 'badge-success badge-soft',
    'Rechazado' => 'badge-error badge-soft',
    'En nueva revisión' => 'badge-info badge-soft',
    default => 'badge-warning badge-soft',
};
?>

<?= view('partials/page_header', ['title' => $title, 'description' => $description, 'breadcrumbs' => [['label' => 'Mis comprobantes', 'url' => site_url('portal/reportes-pago')], ['label' => 'Comprobante #' . (int) $statement['id']]]], ['saveData' => false]) ?>

<section class="grid items-start gap-6 lg:grid-cols-12">
    <article class="card card-border bg-base-100 lg:col-span-8"><div class="card-body"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="eyebrow">Estado de revisión</p><h2 class="card-title mt-1"><?= esc($statement['institucion'] . ' · ' . $statement['alias']) ?></h2></div><span class="badge <?= esc($statusClass, 'attr') ?>"><?= esc($statement['estado']) ?></span></div><div class="stats stats-vertical mt-6 border border-base-300 sm:stats-horizontal"><div class="stat"><div class="stat-title">Monto reportado</div><div class="stat-value font-data text-2xl">$<?= esc($statement['monto']) ?></div></div><div class="stat"><div class="stat-title">Fecha bancaria</div><div class="stat-value font-data text-xl"><?= esc($statement['fecha_local']) ?></div></div><div class="stat"><div class="stat-title">Referencia</div><div class="stat-value font-data break-all text-lg"><?= esc($statement['referencia_reportada']) ?></div></div></div><?php if ($statement['observacion'] !== null): ?><div class="mt-6"><h3 class="font-semibold">Tu observación</h3><p class="mt-2 whitespace-pre-line text-base-content/70"><?= esc($statement['observacion']) ?></p></div><?php endif ?></div></article>
    <aside class="card card-border bg-base-100 lg:col-span-4"><div class="card-body"><h2 class="card-title">Evidencia privada</h2><?php if (!$statement['files']): ?><p class="text-sm text-base-content/70">No existe un archivo adjunto.</p><?php else: ?><ul class="mt-2 space-y-3"><?php foreach ($statement['files'] as $file): ?><li class="flex items-center gap-3"><i data-lucide="paperclip" class="icon shrink-0" aria-hidden="true"></i><div class="min-w-0 flex-1"><p class="truncate font-medium"><?= esc($file['nombre_original']) ?></p><p class="font-data text-xs text-base-content/60"><?= esc(number_format((int) $file['tamano_bytes'] / 1024, 1)) ?> KB</p></div><a class="btn btn-sm" href="<?= site_url('portal/reportes-pago/archivos/' . (int) $file['id']) ?>">Descargar</a></li><?php endforeach ?></ul><?php endif ?><p class="font-data mt-5 text-xs text-base-content/60">Reportado el <?= esc($statement['reportado_en_local']) ?></p></div></aside>
</section>

<section class="card card-border mt-6 bg-base-100" aria-labelledby="review-history-title"><div class="card-body p-0"><header class="section-heading"><div><h2 id="review-history-title" class="card-title">Historial de revisión</h2><p class="mt-1 text-sm text-base-content/70">Cada decisión conserva el resultado y la explicación comunicada por el negocio.</p></div></header><?php if (!$statement['reviews']): ?><div class="p-6"><div class="alert alert-info alert-soft" role="status"><i data-lucide="clock-3" class="icon" aria-hidden="true"></i><span>El comprobante todavía está pendiente de revisión.</span></div></div><?php else: ?><ol class="divide-y divide-base-300"><?php foreach ($statement['reviews'] as $review): ?><li class="p-6"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><p class="font-semibold">Revisión <span class="font-data">#<?= (int) $review['revision'] ?></span> · <?= esc($review['resultado_label']) ?></p><time class="font-data text-sm text-base-content/60"><?= esc($review['registrada_en_local']) ?></time></div><p class="mt-3 whitespace-pre-line text-base-content/70"><?= esc($review['notas']) ?></p></li><?php endforeach ?></ol><?php endif ?></div></section>

<div class="mt-6 flex justify-end"><a class="btn" href="<?= site_url('portal/reportes-pago') ?>">Volver a Mis comprobantes</a></div>

<?= $this->endSection() ?>
