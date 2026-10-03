<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', [
    'context' => 'Directorio',
    'title' => $title,
    'description' => $description,
    'primaryAction' => $canCreate ? ['label' => 'Nuevo cliente', 'url' => site_url('clientes/nuevo'), 'icon' => 'plus', 'dialog' => 'client-form-modal'] : null,
], ['saveData' => false]) ?>

<section class="card card-border bg-base-100" aria-labelledby="client-results-title">
    <h2 id="client-results-title" class="sr-only">Resultados del directorio</h2>
    <form method="get" class="filter-toolbar" data-datatable-filter="clients">
        <label class="input min-w-0 flex-1">
            <i data-lucide="search" class="icon" aria-hidden="true"></i>
            <input name="q" value="<?= esc($q, 'attr') ?>" aria-label="Buscar por nombre o identificación" placeholder="Nombre o identificación…" maxlength="100" autocomplete="off">
        </label>
        <select name="estado" aria-label="Estado" class="select">
            <option value="">Todos los estados</option>
            <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Activos</option>
            <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactivos</option>
        </select>
        <button class="btn" type="submit">Buscar</button>
    </form>

    <?php if (!$rows): ?>
        <?= view('partials/empty_state', [
            'icon' => 'users',
            'title' => 'No hay clientes para mostrar',
            'description' => 'Registra un cliente o ajusta los filtros de búsqueda.',
            'action' => $canCreate ? ['label' => 'Nuevo cliente', 'url' => site_url('clientes/nuevo')] : null,
        ], ['saveData' => false]) ?>
    <?php else: ?>
        <div class="hidden overflow-x-auto md:block">
            <table class="table" data-datatable="clients" data-source="<?= site_url('clientes/datos') ?>" data-records-base="<?= site_url('clientes') ?>" data-export-csv="<?= site_url('clientes/exportar.csv') ?>" data-export-xlsx="<?= site_url('clientes/exportar.xlsx') ?>" data-can-edit="<?= $canEdit ? 'true' : 'false' ?>">
                <caption class="sr-only">Directorio de clientes</caption>
                <thead><tr><th scope="col">Cliente</th><th scope="col">Identificación</th><th scope="col">Estado</th><th scope="col" class="dt-actions"><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><div class="max-w-md break-words font-semibold"><?= esc($row['nombre']) ?></div><span class="font-data text-xs text-base-content/70">Cliente #<?= $row['id'] ?></span></td>
                        <td class="font-data"><?= esc($row['identificacion'] ?: 'No informada') ?></td>
                        <td><span class="badge <?= $row['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $row['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="text-right"><div class="flex justify-end gap-2"><a class="btn" href="<?= site_url('clientes/' . $row['id'] . '/expediente') ?>">Ver<span class="sr-only"> expediente de <?= esc($row['nombre']) ?></span></a><?php if ($canEdit): ?><a class="btn" href="<?= site_url('clientes/' . $row['id']) ?>">Editar<span class="sr-only"> <?= esc($row['nombre']) ?></span></a><?php endif ?></div></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Directorio de clientes">
        <?php foreach ($rows as $row): ?>
            <li class="record-card">
                <div class="min-w-0 flex-1">
                    <p class="break-words font-semibold"><?= esc($row['nombre']) ?></p>
                    <div class="mt-1 flex flex-wrap items-center gap-2"><span class="font-data text-sm text-base-content/70"><?= esc($row['identificacion'] ?: 'Sin identificación') ?></span><span class="badge <?= $row['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $row['activo'] ? 'Activo' : 'Inactivo' ?></span></div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a class="btn" href="<?= site_url('clientes/' . $row['id'] . '/expediente') ?>">Abrir<span class="sr-only"> expediente de <?= esc($row['nombre']) ?></span></a>
                    <?php if ($canEdit): ?><a class="btn btn-square" href="<?= site_url('clientes/' . $row['id']) ?>" aria-label="Editar <?= esc($row['nombre'], 'attr') ?>"><i data-lucide="pencil" class="icon" aria-hidden="true"></i></a><?php endif ?>
                </div>
            </li>
        <?php endforeach ?>
        </ul>
    <?php endif ?>

    <div class="datatable-fallback-pagination">
        <?= view('partials/pagination', ['total' => $total, 'page' => $page, 'base' => 'clientes', 'params' => ['q' => $q, 'estado' => $status]], ['saveData' => false]) ?>
    </div>
</section>

<?php
$modal = $clientModal ?? [
    'title' => 'Nuevo cliente',
    'description' => 'Completa lo que conoces. La identificación y el contacto son opcionales.',
    'client' => [],
    'autoOpen' => false,
    'canChangeState' => false,
];
?>
<?php if ($canCreate || !empty($modal['client'])): ?>
    <?= view('clients/form', $modal, ['saveData' => false]) ?>
<?php endif ?>

<?= $this->endSection() ?>
