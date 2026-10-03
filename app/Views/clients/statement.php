<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$displayDate = static function (string $value): string {
    try { return (new DateTimeImmutable($value))->format('d/m/Y'); }
    catch (Throwable) { return $value; }
};
$kindClass = static fn (string $kind): string => match ($kind) {
    'DOCUMENTO' => 'badge-warning badge-soft',
    'COBRO' => 'badge-success badge-soft',
    default => 'badge-error badge-soft',
};
?>

<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $client['nombre'], 'url' => site_url('clientes/' . $clientId . '/expediente')],
        ['label' => 'Estado de cuenta'],
    ],
], ['saveData' => false]) ?>

<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>

<?php if ($filterErrors): ?>
<div class="alert alert-error mb-5" role="alert"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><span><?= esc(implode(' ', $filterErrors)) ?></span></div>
<?php endif ?>

<section class="stats stats-vertical mb-5 w-full border border-base-300 bg-base-100 sm:stats-horizontal" aria-label="Resumen del estado de cuenta">
    <div class="stat"><div class="stat-title">Saldo neto al corte</div><div class="stat-value font-data text-2xl"><?= esc($closingBalance) ?></div><div class="stat-desc"><?= $filters['hasta'] ? 'Al ' . esc($displayDate($filters['hasta'])) : 'A la fecha actual' ?></div></div>
    <div class="stat"><div class="stat-title">Cartera pendiente actual</div><div class="stat-value font-data text-2xl"><?= esc($portfolioBalance) ?></div><div class="stat-desc">Cuotas confirmadas sin cancelar</div></div>
    <div class="stat"><div class="stat-title">Cobros disponibles</div><div class="stat-value font-data text-2xl"><?= esc($availableBalance) ?></div><div class="stat-desc">Recibidos y todavía no aplicados</div></div>
    <div class="stat"><div class="stat-title">Movimientos encontrados</div><div class="stat-value font-data text-2xl"><?= (int) $total ?></div><div class="stat-desc">Según los filtros visibles</div></div>
</section>

<section class="card card-border mb-5 bg-base-100"><div class="card-body">
    <form method="get" action="<?= site_url('clientes/' . $clientId . '/estado-cuenta') ?>" class="grid gap-4 md:grid-cols-4 md:items-end">
        <fieldset class="fieldset"><legend class="fieldset-legend">Desde</legend><input class="input w-full" type="date" name="desde" value="<?= esc($filters['desde'], 'attr') ?>"></fieldset>
        <fieldset class="fieldset"><legend class="fieldset-legend">Hasta</legend><input class="input w-full" type="date" name="hasta" value="<?= esc($filters['hasta'], 'attr') ?>"></fieldset>
        <fieldset class="fieldset"><legend class="fieldset-legend">Tipo de movimiento</legend><select class="select w-full" name="tipo"><?php foreach ($typeOptions as $value => $label): ?><option value="<?= esc($value, 'attr') ?>" <?= $filters['tipo'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select></fieldset>
        <div class="grid grid-cols-2 gap-2"><a class="btn" href="<?= site_url('clientes/' . $clientId . '/estado-cuenta') ?>">Limpiar</a><button class="btn btn-primary" type="submit"><i data-lucide="list-filter" class="icon" aria-hidden="true"></i>Filtrar</button></div>
    </form>
    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 border-t border-base-300 pt-4 text-sm"><span>Saldo inicial: <strong class="font-data"><?= esc($openingBalance) ?></strong></span><span>Cargos del período: <strong class="font-data"><?= esc($periodDebits) ?></strong></span><span>Cobros del período: <strong class="font-data"><?= esc($periodCredits) ?></strong></span></div>
    <p class="mt-2 text-xs text-base-content/70">El saldo y los totales del período incluyen todos los movimientos; el filtro de tipo controla únicamente las filas visibles.</p>
</div></section>

<?php if (!$rows): ?>
<?= view('partials/empty_state', ['icon' => 'file-bar-chart', 'title' => 'Sin movimientos en este corte', 'description' => $filterErrors ? 'Corrige las fechas para consultar el estado de cuenta.' : 'Ajusta los filtros o registra un documento confirmado para iniciar el historial.'], ['saveData' => false]) ?>
<?php else: ?>
<section class="card card-border min-w-0 bg-base-100"><div class="card-body p-0">
    <header class="section-heading"><div><h2 class="card-title">Libro mayor</h2><p class="mt-1 text-sm text-base-content/70">El saldo muestra el resultado inmediatamente después de cada movimiento.</p></div></header>
    <div class="hidden overflow-x-auto md:block">
        <table class="table"><caption class="sr-only">Movimientos del estado de cuenta de <?= esc($client['nombre']) ?></caption><thead><tr><th scope="col">Fecha</th><th scope="col">Tipo</th><th scope="col">Referencia</th><th scope="col">Detalle</th><th scope="col" class="text-right">Cargo</th><th scope="col" class="text-right">Cobro</th><th scope="col" class="text-right">Saldo</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr><td class="font-data whitespace-nowrap"><?= esc($displayDate($row['date'])) ?></td><td><span class="badge <?= esc($kindClass($row['kind']), 'attr') ?>"><?= esc($row['kind']) ?></span></td><td><a class="link font-data" href="<?= esc($row['url'], 'attr') ?>"><?= esc($row['reference']) ?></a></td><td><?= esc($row['description']) ?></td><td class="font-data text-right"><?= esc($row['debit']) ?></td><td class="font-data text-right"><?= esc($row['credit']) ?></td><td class="font-data text-right font-semibold"><?= esc($row['balance']) ?></td></tr><?php endforeach ?>
        </tbody></table>
    </div>
    <ul class="divide-y divide-base-300 md:hidden" aria-label="Movimientos del estado de cuenta">
        <?php foreach ($rows as $row): ?><li class="list-row items-start"><div class="list-col-grow min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="badge <?= esc($kindClass($row['kind']), 'attr') ?>"><?= esc($row['kind']) ?></span><span class="font-data text-sm text-base-content/70"><?= esc($displayDate($row['date'])) ?></span></div><a class="link mt-2 block font-data font-semibold" href="<?= esc($row['url'], 'attr') ?>"><?= esc($row['reference']) ?></a><p class="mt-1 text-sm text-base-content/70"><?= esc($row['description']) ?></p></div><dl class="shrink-0 text-right text-sm"><div><dt class="text-base-content/70"><?= $row['debit'] !== '—' ? 'Cargo' : 'Cobro' ?></dt><dd class="font-data font-semibold"><?= esc($row['debit'] !== '—' ? $row['debit'] : $row['credit']) ?></dd></div><div class="mt-2"><dt class="text-base-content/70">Saldo</dt><dd class="font-data"><?= esc($row['balance']) ?></dd></div></dl></li><?php endforeach ?>
    </ul>
</div></section>

<?php endif ?>

<?= $this->endSection() ?>
