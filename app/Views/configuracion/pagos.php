<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$editing = $account !== null;
$value = static function (string $key, string $default = '') use ($account): string {
    $value = old($key, $account[$key] ?? $default);
    return is_string($value) ? $value : $default;
};
?>
<?= view('partials/page_header', ['title' => $title, 'description' => $description, 'breadcrumbs' => $breadcrumbs], ['saveData' => false]) ?>
<?php if ($errors): ?><div class="alert alert-error mb-5" role="alert" tabindex="-1"><div><h2 class="font-semibold">Revisa la cuenta bancaria</h2><ul class="list-disc pl-5"><?php foreach ($errors as $key => $error): ?><li><a href="#<?= esc($key, 'attr') ?>"><?= esc($error) ?></a></li><?php endforeach ?></ul></div></div><?php endif ?>
<div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
    <section class="card card-border min-w-0 self-start bg-base-100"><div class="card-body">
        <h2 class="card-title">Cuentas receptoras</h2>
        <p class="text-sm text-base-content/70">Las cuentas activas aparecen en los cobros de todos los usuarios autorizados. Desactivarlas conserva su historial.</p>
        <?php if (!$accounts): ?><p class="my-4">Aún no hay cuentas. Completa el formulario para registrar la primera.</p><?php endif ?>
        <ul class="mt-4 grid gap-4">
        <?php foreach ($accounts as $row): ?>
            <li class="rounded-box border border-base-300 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="min-w-0 break-words font-semibold"><?= esc($row['alias']) ?></h3><span class="badge <?= $row['activa'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $row['activa'] ? 'Activa' : 'Inactiva' ?></span></div>
                <p class="mt-2 break-words"><?= esc($row['institucion']) ?></p>
                <p class="font-data break-all"><?= esc($row['numero_cuenta']) ?></p>
                <p class="mt-1 text-sm break-words"><?= esc($row['titular'] ?: 'Titular pendiente de completar') ?> · <?= esc(['AHORROS' => 'Ahorros', 'CORRIENTE' => 'Corriente', 'OTRA' => 'Otra'][$row['tipo']] ?? $row['tipo']) ?></p>
                <div class="mt-3 flex flex-wrap gap-3">
                    <a class="btn btn-sm" href="<?= site_url('configuracion/cuentas-bancarias/' . (int) $row['id'] . '/editar') ?>">Editar cuenta</a>
                    <form method="post" action="<?= site_url('configuracion/cuentas-bancarias/' . (int) $row['id'] . '/estado') ?>" data-confirm="<?= $row['activa'] ? 'La cuenta dejará de admitir nuevos cobros. Su historial se conservará.' : 'La cuenta volverá a estar disponible para nuevos cobros.' ?>">
                        <?= csrf_field() ?><input type="hidden" name="activa" value="<?= $row['activa'] ? '0' : '1' ?>">
                        <button type="submit" class="btn btn-sm"><?= $row['activa'] ? 'Desactivar cuenta' : 'Reactivar cuenta' ?></button>
                    </form>
                </div>
            </li>
        <?php endforeach ?>
        </ul>
    </div></section>
    <section class="card card-border min-w-0 self-start bg-base-100"><div class="card-body">
        <h2 class="card-title"><?= $editing ? 'Editar cuenta bancaria' : 'Nueva cuenta bancaria' ?></h2>
        <?php if (!empty($account['used'])): ?><p class="text-sm text-base-content/70">El banco y el número están protegidos porque esta cuenta ya tiene cobros. Para otro número, registra una cuenta nueva.</p><?php endif ?>
        <form method="post" action="<?= site_url($editing ? 'configuracion/cuentas-bancarias/' . (int) $account['id'] : 'configuracion/pagos') ?>" class="grid gap-4">
            <?= csrf_field() ?>
            <?php foreach (['institucion' => ['Banco o institución', 120], 'numero_cuenta' => ['Número de cuenta', 80], 'titular' => ['Titular', 120], 'alias' => ['Nombre para identificar la cuenta', 100]] as $key => [$label, $limit]): ?>
            <label class="form-field" for="<?= $key ?>"><span><?= $label ?></span>
                <input id="<?= $key ?>" class="input w-full <?= isset($errors[$key]) ? 'input-error' : '' ?>" name="<?= $key ?>" type="text" value="<?= esc((string) $value($key), 'attr') ?>" maxlength="<?= $limit ?>" required autocomplete="off" <?= !empty($account['used']) && in_array($key, ['institucion', 'numero_cuenta'], true) ? 'readonly' : '' ?> <?= isset($errors[$key]) ? 'aria-invalid="true" aria-describedby="' . $key . '-error"' : '' ?>>
                <?php if (isset($errors[$key])): ?><span class="field-error" id="<?= $key ?>-error"><?= esc($errors[$key]) ?></span><?php endif ?>
            </label>
            <?php endforeach ?>
            <label class="form-field" for="tipo"><span>Tipo de cuenta</span><select id="tipo" class="select w-full" name="tipo" required><?php foreach (['AHORROS' => 'Ahorros', 'CORRIENTE' => 'Corriente', 'OTRA' => 'Otra'] as $key => $label): ?><option value="<?= $key ?>" <?= $value('tipo', 'AHORROS') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select><?php if (isset($errors['tipo'])): ?><span class="field-error"><?= esc($errors['tipo']) ?></span><?php endif ?></label>
            <p class="text-sm text-base-content/70">Los datos se guardan en el negocio y permanecen al cerrar sesión. Los números conservan sus ceros iniciales.</p>
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Guardar cambios' : 'Crear cuenta bancaria' ?></button>
            <?php if ($editing): ?><a class="btn" href="<?= site_url('configuracion/pagos') ?>">Cancelar edición</a><?php endif ?>
        </form>
    </div></section>
</div>
<?= $this->endSection() ?>
