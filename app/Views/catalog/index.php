<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', [
    'context' => 'Productos y servicios',
    'title' => $title,
    'description' => $description,
    'primaryAction' => ['label' => 'Nuevo ítem', 'url' => site_url('catalogo/nuevo'), 'icon' => 'plus'],
], ['saveData' => false]) ?>

<section class="card card-border bg-base-100" aria-labelledby="catalog-results-title">
    <h2 id="catalog-results-title" class="sr-only">Resultados del catálogo</h2>
    <form method="get" class="filter-toolbar">
        <label class="input min-w-0 flex-1"><i data-lucide="search" class="icon" aria-hidden="true"></i><input name="q" value="<?= esc($q, 'attr') ?>" aria-label="Buscar por nombre o código" placeholder="Nombre o código…" maxlength="100" autocomplete="off"></label>
        <button class="btn" type="submit">Buscar</button>
    </form>

    <?php if (!$rows): ?>
        <?= view('partials/empty_state', ['icon' => 'package', 'title' => 'Tu catálogo empieza aquí', 'description' => 'Añade los productos o servicios que ofreces.', 'action' => ['label' => 'Nuevo ítem', 'url' => site_url('catalogo/nuevo')]], ['saveData' => false]) ?>
    <?php else: ?>
        <div class="hidden overflow-x-auto md:block">
            <table class="table">
                <caption class="sr-only">Productos y servicios</caption>
                <thead><tr><th scope="col">Concepto</th><th scope="col">Tipo</th><th scope="col" class="text-right">Precio USD</th><th scope="col">Estado</th><th scope="col" class="text-right">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr><td><strong class="break-words"><?= esc($row['nombre']) ?></strong><div class="font-data text-xs text-base-content/70"><?= esc($row['codigo']) ?></div></td><td><?= $row['tipo'] === 'SERVICIO' ? 'Servicio' : 'Producto' ?></td><td class="font-data text-right"><?= $row['precio_referencia'] === null ? 'No informado' : esc($row['precio_referencia']) ?></td><td><span class="badge <?= $row['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $row['activo'] ? 'Activo' : 'Inactivo' ?></span></td><td class="text-right"><div class="flex justify-end gap-2"><a class="btn" href="<?= site_url('catalogo/' . $row['id'] . '/detalle') ?>" data-dialog-open="catalog-item-<?= (int) $row['id'] ?>">Ver<span class="sr-only"> detalle de <?= esc($row['nombre']) ?></span></a><a class="btn" href="<?= site_url('catalogo/' . $row['id']) ?>">Editar<span class="sr-only"> <?= esc($row['nombre']) ?></span></a></div></td></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Productos y servicios">
        <?php foreach ($rows as $row): ?>
            <li class="record-card"><div class="min-w-0"><p class="break-words font-semibold"><?= esc($row['nombre']) ?></p><p class="font-data mt-1 text-sm text-base-content/70"><?= esc($row['codigo']) ?></p></div><div class="text-right"><p class="font-data font-medium"><?= $row['precio_referencia'] === null ? 'Sin precio' : '$' . esc($row['precio_referencia']) ?></p><div class="mt-2 flex flex-wrap justify-end gap-2"><a class="btn" href="<?= site_url('catalogo/' . $row['id'] . '/detalle') ?>" data-dialog-open="catalog-item-<?= (int) $row['id'] ?>">Ver<span class="sr-only"> detalle de <?= esc($row['nombre']) ?></span></a><a class="btn" href="<?= site_url('catalogo/' . $row['id']) ?>">Editar<span class="sr-only"> <?= esc($row['nombre']) ?></span></a></div></div></li>
        <?php endforeach ?>
        </ul>
        <?php foreach ($rows as $row): ?>
        <dialog id="catalog-item-<?= (int) $row['id'] ?>" class="modal">
            <div class="modal-box max-w-xl">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0"><p class="font-data text-sm text-base-content/70"><?= esc($row['codigo']) ?></p><h2 class="mt-1 break-words text-xl font-semibold"><?= esc($row['nombre']) ?></h2></div>
                    <span class="badge <?= $row['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $row['activo'] ? 'Activo' : 'Inactivo' ?></span>
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-4 border-y border-base-300 py-4">
                    <div><dt class="text-sm text-base-content/70">Tipo</dt><dd class="mt-1 font-medium"><?= $row['tipo'] === 'SERVICIO' ? 'Servicio' : 'Producto' ?></dd></div>
                    <div><dt class="text-sm text-base-content/70">Precio de referencia</dt><dd class="font-data mt-1 font-medium"><?= $row['precio_referencia'] === null ? 'No informado' : '$' . esc(number_format((float) $row['precio_referencia'], 2, '.', ',')) ?></dd></div>
                </dl>
                <div class="modal-action">
                    <form method="dialog"><button class="btn">Cerrar</button></form>
                    <a class="btn btn-primary" href="<?= site_url('catalogo/' . $row['id']) ?>"><i data-lucide="pencil" class="icon" aria-hidden="true"></i>Editar ítem</a>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button>Cerrar detalle</button></form>
        </dialog>
        <?php endforeach ?>
    <?php endif ?>

</section>

<?= $this->endSection() ?>
