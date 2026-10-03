<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $key, string $default = '') => old($key, $item[$key] ?? $default);
$editing = isset($item['id']);
?>

<?= view('partials/page_header', ['context' => 'Productos y servicios', 'title' => $title, 'description' => $description, 'breadcrumbs' => $breadcrumbs], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div id="form-errors" class="alert alert-error mb-6" role="alert" tabindex="-1" aria-labelledby="form-errors-title">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <div><h2 id="form-errors-title" class="font-semibold">Revisa los datos indicados</h2><ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $key => $error): ?><li><a class="underline" href="#<?= esc((string) $key, 'attr') ?>"><?= esc($error) ?></a></li><?php endforeach ?></ul></div>
</div>
<?php endif ?>

<div class="form-layout">
    <form method="post" action="<?= site_url($editing ? 'catalogo/' . $item['id'] : 'catalogo') ?>" class="card card-border form-card bg-base-100">
        <div class="card-body">
            <?= csrf_field() ?><input type="hidden" name="version" value="<?= esc($value('version', '1'), 'attr') ?>">
            <fieldset class="fieldset">
                <legend class="fieldset-legend text-lg">Datos del ítem</legend>
                <div class="form-grid">
                <?php foreach (['nombre' => ['Nombre', 'text', 160], 'codigo' => ['Código', 'text', 80], 'precio_referencia' => ['Precio de referencia (USD)', 'text', 18]] as $key => [$label, $type, $max]): ?>
                    <label class="form-field" for="<?= $key ?>"><span><?= $label ?><?= $key !== 'precio_referencia' ? ' *' : '' ?></span><input class="input w-full <?= isset($errors[$key]) ? 'input-error' : '' ?>" id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" maxlength="<?= $max ?>" value="<?= esc($value($key), 'attr') ?>" autocomplete="off" <?= $key !== 'precio_referencia' ? 'required' : 'inputmode="decimal" placeholder="0.00"' ?> <?= isset($errors[$key]) ? 'aria-invalid="true" aria-describedby="' . $key . '-error"' : '' ?>><?php if (isset($errors[$key])): ?><span class="field-error" id="<?= $key ?>-error"><?= esc($errors[$key]) ?></span><?php endif ?></label>
                <?php endforeach ?>
                    <label class="form-field" for="tipo"><span>Tipo</span><select class="select w-full" id="tipo" name="tipo" autocomplete="off"><option value="SERVICIO" <?= $value('tipo') === 'SERVICIO' ? 'selected' : '' ?>>Servicio</option><option value="PRODUCTO" <?= $value('tipo') === 'PRODUCTO' ? 'selected' : '' ?>>Producto</option></select></label>
                    <?php if ($editing): ?><label class="form-field" for="activo"><span>Estado</span><select class="select w-full" name="activo" id="activo" autocomplete="off"><option value="1" <?= $value('activo') === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= $value('activo') === '0' ? 'selected' : '' ?>>Inactivo</option></select></label><?php endif ?>
                </div>
            </fieldset>
            <div class="form-actions"><a class="btn" href="<?= site_url('catalogo') ?>">Cancelar</a><button type="submit" class="btn btn-primary">Guardar ítem</button></div>
        </div>
    </form>

    <aside class="card card-border form-summary bg-base-100" aria-labelledby="catalog-form-help"><div class="card-body"><h2 id="catalog-form-help" class="card-title">Referencia comercial</h2><p class="text-sm text-base-content/70">El precio sirve como referencia al preparar operaciones. No representa una deuda ni un cobro.</p><p class="font-data mt-2 text-sm text-base-content/70">Moneda: USD</p></div></aside>
</div>

<?= $this->endSection() ?>
