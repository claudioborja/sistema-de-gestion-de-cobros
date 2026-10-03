<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?php
$items = $items ?? [];
$reportMode = $reportMode ?? null;
$statusClass = static fn (string $status): string => match ($status) {
    'Vencida' => 'badge-error badge-soft',
    'Por vencer', 'Pendiente' => 'badge-warning badge-soft',
    'Disponible', 'Recuperada' => 'badge-success badge-soft',
    default => 'badge-ghost',
};
?>

<?= view('partials/page_header', [
    'context' => $reportMode === 'expenses' ? 'Caja' : 'Cartera',
    'title' => $title,
    'description' => $description,
    'primaryAction' => $reportMode === 'cartera'
        ? ['label' => 'Exportar CSV', 'url' => site_url('reportes/cartera.csv'), 'icon' => 'download']
        : null,
], ['saveData' => false]) ?>

<div class="grid gap-6">
    <?php if ($reportMode === 'cartera'): ?>
        <?php $pending = count(array_filter($items, static fn (array $item): bool => in_array(($item['estado'] ?? ''), ['Pendiente', 'Por vencer'], true))); ?>
        <?php $overdue = count(array_filter($items, static fn (array $item): bool => ($item['estado'] ?? '') === 'Vencida')); ?>
        <div class="grid gap-4 md:grid-cols-3">
            <article class="stat card card-border bg-base-100"><div class="stat-title">Obligaciones visibles</div><div class="stat-value font-data text-3xl"><?= count($items) ?></div><div class="stat-desc">Según la consulta actual</div></article>
            <article class="stat card card-border bg-base-100"><div class="stat-title">Pendientes</div><div class="stat-value font-data text-3xl"><?= $pending ?></div><div class="stat-desc">Por vencer o en seguimiento</div></article>
            <article class="stat card card-border bg-base-100"><div class="stat-title">Vencidas</div><div class="stat-value font-data text-3xl"><?= $overdue ?></div><div class="stat-desc">Requieren gestión prioritaria</div></article>
        </div>
    <?php endif ?>

    <?php if ($reportMode !== 'expenses'): ?>
    <form method="get" class="filter-toolbar rounded-box border border-base-300 bg-base-100" aria-label="Filtrar cartera">
        <label class="input min-w-0 flex-1"><i data-lucide="search" class="icon" aria-hidden="true"></i><input name="q" value="<?= esc((string) ($query ?? ''), 'attr') ?>" placeholder="Cliente u obligación…" aria-label="Buscar en cartera"></label>
        <select class="select" name="estado" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option>Vencida</option><option>Por vencer</option><option>Pendiente</option><option>Recuperada</option></select>
        <button class="btn" type="submit">Filtrar</button>
    </form>
    <?php else: ?>
    <section class="card card-border bg-base-100"><div class="card-body"><div class="flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-full bg-success/15 text-success"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span><div><h2 class="card-title">Fondo disponible</h2><p class="mt-1 text-sm text-base-content/70">Movimientos internos registrados para la jornada.</p></div></div></div></section>
    <?php endif ?>

    <?php if (!$items): ?>
        <?= view('partials/empty_state', ['icon' => 'wallet', 'title' => 'No hay obligaciones para mostrar', 'description' => 'Prueba con otro estado o vuelve a cargar la cartera.'], ['saveData' => false]) ?>
    <?php else: ?>
        <div class="hidden overflow-x-auto rounded-box border border-base-300 bg-base-100 md:block">
            <table class="table" data-datatable="local"><caption class="sr-only">Obligaciones de cartera</caption>
                <thead><tr><th scope="col"><?= $reportMode === 'expenses' ? 'Movimiento' : 'Obligación' ?></th><th scope="col"><?= $reportMode === 'expenses' ? 'Concepto' : 'Cliente' ?></th><th scope="col"><?= $reportMode === 'expenses' ? 'Fecha' : 'Vencimiento' ?></th><th scope="col"><?= $reportMode === 'expenses' ? 'Monto' : 'Saldo' ?></th><th scope="col">Estado</th><?php if ($reportMode !== 'expenses'): ?><th scope="col" class="dt-actions"><span class="sr-only">Acciones</span></th><?php endif ?></tr></thead>
                <tbody><?php foreach ($items as $item): ?><tr>
                    <th scope="row" class="font-data">#<?= esc((string) $item['id']) ?></th><td><?= esc($item['cliente']) ?></td><td class="font-data whitespace-nowrap"><?= esc($item['vencimiento']) ?></td><td class="font-data"><?= esc($item['saldo']) ?></td><td><span class="badge <?= $statusClass((string) $item['estado']) ?>"><?= esc($item['estado']) ?></span></td><?php if ($reportMode !== 'expenses'): ?><td class="text-right"><a class="btn btn-sm" href="<?= site_url('obligaciones/' . $item['id']) ?>">Abrir</a></td><?php endif ?>
                </tr><?php endforeach ?></tbody>
            </table>
        </div>
        <ul class="divide-y divide-base-300 rounded-box border border-base-300 bg-base-100 md:hidden" aria-label="Obligaciones de cartera">
            <?php foreach ($items as $item): ?><li class="record-card"><div class="min-w-0"><p class="font-semibold">#<?= esc((string) $item['id']) ?> · <?= esc($item['cliente']) ?></p><p class="mt-1 text-sm text-base-content/70"><?= $reportMode === 'expenses' ? 'Fecha' : 'Vence' ?> <?= esc($item['vencimiento']) ?> · <span class="font-data"><?= esc($item['saldo']) ?></span></p></div><div class="flex flex-col items-end gap-2"><span class="badge <?= $statusClass((string) $item['estado']) ?>"><?= esc($item['estado']) ?></span><?php if ($reportMode !== 'expenses'): ?><a class="btn btn-sm" href="<?= site_url('obligaciones/' . $item['id']) ?>">Abrir</a><?php endif ?></div></li><?php endforeach ?>
        </ul>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
