<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?php
$items = $items ?? [];
$filters = $filters ?? ['q' => '', 'estado' => '', 'page' => 1];
$summary = $summary ?? ['balance' => '0.00', 'overdue' => 0, 'dueSoon' => 0];
$statusClass = static fn (string $status): string => match ($status) {
    'Vencida' => 'badge-error badge-soft',
    'Por vencer', 'Pendiente' => 'badge-warning badge-soft',
    default => 'badge-success badge-soft',
};
$exportParams = array_filter(['q' => $filters['q'], 'estado' => $filters['estado']], static fn (string $value): bool => $value !== '');
$exportQuery = $exportParams ? '?' . http_build_query($exportParams) : '';
?>

<?= view('partials/page_header', [
    'context' => 'Reportes financieros',
    'title' => $title,
    'description' => $description,
    'primaryAction' => $total > 0 ? ['label' => 'Descargar CSV', 'url' => site_url('reportes/cartera.csv') . $exportQuery, 'icon' => 'download'] : null,
], ['saveData' => false]) ?>

<nav class="mb-6 overflow-x-auto" aria-label="Navegación de reportes"><div class="tabs tabs-box w-max min-w-full sm:min-w-0"><a class="tab" href="<?= site_url('reportes') ?>"><i data-lucide="chart-no-axes-combined" class="icon" aria-hidden="true"></i>Resumen</a><a class="tab tab-active" aria-current="page" href="<?= site_url('reportes/cartera') ?>"><i data-lucide="wallet" class="icon" aria-hidden="true"></i>Cartera</a><a class="tab" href="<?= site_url('reportes/pagos') ?>"><i data-lucide="receipt" class="icon" aria-hidden="true"></i>Pagos</a></div></nav>

<form class="card card-border mb-6 bg-base-100" method="get" action="<?= site_url('reportes/cartera') ?>">
    <div class="card-body gap-4 lg:flex-row lg:items-end">
        <fieldset class="fieldset min-w-0 flex-1"><legend class="fieldset-legend">Buscar</legend><input class="input w-full" type="search" name="q" maxlength="100" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Cliente, concepto o documento"></fieldset>
        <fieldset class="fieldset w-full lg:w-56"><legend class="fieldset-legend">Estado</legend><select class="select w-full" name="estado"><option value="">Todos</option><option value="vencida" <?= $filters['estado'] === 'vencida' ? 'selected' : '' ?>>Vencida</option><option value="por_vencer" <?= $filters['estado'] === 'por_vencer' ? 'selected' : '' ?>>Por vencer (30 días)</option><option value="pendiente" <?= $filters['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente posterior</option></select></fieldset>
        <div class="card-actions"><a class="btn" href="<?= site_url('reportes/cartera') ?>">Limpiar</a><button class="btn btn-primary" type="submit"><i data-lucide="search" class="icon" aria-hidden="true"></i>Filtrar</button></div>
    </div>
</form>

<section class="stats stats-vertical w-full border border-base-300 bg-base-100 md:stats-horizontal" aria-label="Resumen de cartera">
    <div class="stat"><div class="stat-title">Saldo filtrado</div><div class="stat-value font-data text-3xl">$<?= number_format((float) $summary['balance'], 2) ?></div><div class="stat-desc"><span class="font-data"><?= (int) $total ?></span> obligaciones abiertas</div></div>
    <div class="stat"><div class="stat-title">Por vencer</div><div class="stat-value font-data text-3xl"><?= (int) $summary['dueSoon'] ?></div><div class="stat-desc">Vencen en los próximos 30 días</div></div>
    <div class="stat"><div class="stat-title">Vencidas</div><div class="stat-value font-data text-3xl"><?= (int) $summary['overdue'] ?></div><div class="stat-desc">Con prioridad de cobro</div></div>
</section>

<section class="card card-border mt-6 bg-base-100" aria-labelledby="portfolio-report-title">
    <div class="card-body p-0">
        <header class="section-heading"><div><p class="eyebrow mb-2">Corte de cartera</p><h2 id="portfolio-report-title" class="card-title">Obligaciones por vencimiento</h2><p class="mt-1 text-sm text-base-content/70">Consulta el detalle y continúa la gestión desde cada obligación.</p></div><?php if ($total > 0): ?><div class="join"><a class="btn join-item" href="<?= esc(site_url('reportes/cartera.csv') . $exportQuery, 'attr') ?>">CSV</a><a class="btn join-item" href="<?= esc(site_url('reportes/cartera.xlsx') . $exportQuery, 'attr') ?>">XLSX</a></div><?php endif ?></header>
        <?php if (!$items): ?>
            <?= view('partials/empty_state', ['icon' => 'wallet', 'title' => 'No hay cartera para reportar', 'description' => 'Cuando existan obligaciones, aparecerán en este corte.'], ['saveData' => false]) ?>
        <?php else: ?>
            <div class="hidden overflow-x-auto md:block"><table class="table"><caption class="sr-only">Reporte de cartera por vencimiento</caption><thead><tr><th scope="col">Obligación</th><th scope="col">Cliente</th><th scope="col">Concepto</th><th scope="col">Vencimiento</th><th scope="col">Saldo</th><th scope="col">Antigüedad</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><th scope="row" class="font-data">#<?= (int) $item['id'] ?></th><td><?= esc($item['cliente']) ?></td><td><?= esc($item['concepto']) ?></td><td class="font-data whitespace-nowrap"><?= esc($item['vencimiento'] ?: 'Sin fecha') ?></td><td class="font-data">$<?= number_format((float) $item['saldo'], 2) ?></td><td><?= esc($item['antiguedad']) ?></td><td><span class="badge <?= $statusClass((string) $item['estado']) ?>"><?= esc($item['estado']) ?></span></td><td class="text-right"><a class="btn btn-sm" href="<?= site_url('obligaciones/' . (int) $item['id']) ?>">Abrir</a></td></tr><?php endforeach ?></tbody></table></div>
            <ul class="divide-y divide-base-300 md:hidden" aria-label="Reporte de cartera por vencimiento"><?php foreach ($items as $item): ?><li class="record-card"><div class="min-w-0"><p class="font-semibold">#<?= (int) $item['id'] ?> · <?= esc($item['cliente']) ?></p><p class="mt-1 text-sm text-base-content/70"><?= esc($item['concepto']) ?></p><p class="font-data mt-1 text-sm text-base-content/70"><?= esc($item['vencimiento'] ?: 'Sin vencimiento') ?> · $<?= number_format((float) $item['saldo'], 2) ?></p></div><div class="flex flex-col items-end gap-2"><span class="badge <?= $statusClass((string) $item['estado']) ?>"><?= esc($item['estado']) ?></span><span class="text-xs text-base-content/70"><?= esc($item['antiguedad']) ?></span><a class="btn btn-sm" href="<?= site_url('obligaciones/' . (int) $item['id']) ?>">Abrir</a></div></li><?php endforeach ?></ul>
            <?= view('partials/pagination', ['total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'base' => 'reportes/cartera', 'params' => $exportParams], ['saveData' => false]) ?>
        <?php endif ?>
    </div>
</section>

<?= $this->endSection() ?>
