<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', [
    'context' => 'Detalle de ítem',
    'title' => $item['nombre'],
    'description' => $description,
    'primaryAction' => ['label' => 'Editar ítem', 'url' => site_url('catalogo/' . $item['id']), 'icon' => 'pencil'],
], ['saveData' => false]) ?>

<section class="card card-border bg-base-100" aria-labelledby="commercial-title">
    <div class="card-body"><div class="flex flex-wrap items-center justify-between gap-4"><h2 id="commercial-title" class="card-title">Ficha comercial</h2><div class="flex flex-wrap items-center gap-2"><a class="btn btn-sm" href="<?= site_url('catalogo') ?>">Volver</a><span class="badge <?= $item['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?> badge-lg"><?= $item['activo'] ? 'Activo' : 'Inactivo' ?></span></div></div>
        <dl class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-base-content/70">Código</dt><dd class="font-data mt-1 break-words text-lg font-medium"><?= esc($item['codigo']) ?></dd></div>
            <div><dt class="text-sm text-base-content/70">Tipo</dt><dd class="mt-1 text-lg font-medium"><?= $item['tipo'] === 'SERVICIO' ? 'Servicio' : 'Producto' ?></dd></div>
            <div><dt class="text-sm text-base-content/70">Precio de referencia</dt><dd class="font-data mt-1 text-lg font-medium"><?= $item['precio_referencia'] === null ? 'No informado' : '$' . esc(number_format((float) $item['precio_referencia'], 2, '.', ',')) ?></dd></div>
            <div><dt class="text-sm text-base-content/70">Versión</dt><dd class="font-data mt-1 text-lg font-medium"><?= $item['version'] ?></dd></div>
        </dl>
    </div>
</section>

<?= $this->endSection() ?>
