<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$editing = isset($register['id']);
$value = static function (string $key, string $default = '') use ($register): string {
    $input = old($key, $register[$key] ?? $default, false);
    return is_scalar($input) ? (string) $input : '';
};
?>
<?= view('partials/page_header', ['title' => $title, 'description' => $description, 'breadcrumbs' => $breadcrumbs], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div id="form-errors" class="alert alert-error mb-6" role="alert" tabindex="-1" aria-labelledby="form-errors-title">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <div><h2 id="form-errors-title" class="font-semibold">Revisa los datos indicados</h2><ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $key => $error): ?><li><?php if (in_array($key, ['codigo', 'nombre', 'activa'], true)): ?><a class="underline" href="#<?= esc($key, 'attr') ?>"><?= esc($error) ?></a><?php else: ?><?= esc($error) ?><?php endif ?></li><?php endforeach ?></ul></div>
</div>
<?php endif ?>
<?php if ($editing && isset($errors['version'])): ?><p class="mb-4"><a class="link" href="<?= site_url('cajas/' . (int) $register['id'] . '/editar') ?>">Cargar los datos actuales de esta caja</a> antes de volver a guardar.</p><?php endif ?>

<form method="post" action="<?= site_url($editing ? 'cajas/' . (int) $register['id'] : 'cajas') ?>" class="card card-border form-card bg-base-100">
    <div class="card-body">
        <?= csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="version" value="<?= esc($value('version'), 'attr') ?>"><?php endif ?>
        <fieldset class="fieldset">
            <legend class="fieldset-legend text-lg">Identificación de la caja</legend>
            <div class="form-grid">
                <label class="form-field" for="codigo"><span>Código *</span><input class="input w-full <?= isset($errors['codigo']) ? 'input-error' : '' ?>" id="codigo" name="codigo" maxlength="30" value="<?= esc($value('codigo'), 'attr') ?>" autocomplete="off" required aria-describedby="codigo-help<?= isset($errors['codigo']) ? ' codigo-error' : '' ?>" <?= isset($errors['codigo']) ? 'aria-invalid="true"' : '' ?>><span id="codigo-help" class="text-sm text-base-content/70">Letras, números, guion o guion bajo. Se guarda en mayúsculas.</span><?php if (isset($errors['codigo'])): ?><span class="field-error" id="codigo-error"><?= esc($errors['codigo']) ?></span><?php endif ?></label>
                <label class="form-field" for="nombre"><span>Nombre *</span><input class="input w-full <?= isset($errors['nombre']) ? 'input-error' : '' ?>" id="nombre" name="nombre" minlength="2" maxlength="120" value="<?= esc($value('nombre'), 'attr') ?>" autocomplete="off" required <?= isset($errors['nombre']) ? 'aria-invalid="true" aria-describedby="nombre-error"' : '' ?>><?php if (isset($errors['nombre'])): ?><span class="field-error" id="nombre-error"><?= esc($errors['nombre']) ?></span><?php endif ?></label>
                <label class="form-field" for="activa"><span>Estado</span><select class="select w-full <?= isset($errors['activa']) ? 'select-error' : '' ?>" id="activa" name="activa" required aria-describedby="activa-help<?= isset($errors['activa']) ? ' activa-error' : '' ?>" <?= isset($errors['activa']) ? 'aria-invalid="true"' : '' ?>><option value="1" <?= $value('activa', '1') === '1' ? 'selected' : '' ?>>Activa</option><option value="0" <?= $value('activa', '1') === '0' ? 'selected' : '' ?>>Inactiva</option></select><span id="activa-help" class="text-sm text-base-content/70">Desactiva una caja que ya no se utilice. Su registro se conserva.</span><?php if (isset($errors['activa'])): ?><span class="field-error" id="activa-error"><?= esc($errors['activa']) ?></span><?php endif ?></label>
            </div>
        </fieldset>
        <div class="form-actions"><a class="btn" href="<?= site_url('cajas') ?>">Cancelar</a><button class="btn btn-primary" type="submit">Guardar caja</button></div>
    </div>
</form>
<?= $this->endSection() ?>
