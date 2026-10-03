<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; $editing = isset($account['id']); $value = static fn (string $key, string $default = '') => old($key, $account[$key] ?? $default); ?>

<?= view('partials/page_header', ['context' => 'Acceso y seguridad', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>

<?php if ($errors): ?><div id="form-errors" class="alert alert-error mb-6" role="alert" tabindex="-1"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><div><h2 class="font-semibold">No se guardaron los cambios</h2><ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $key => $error): ?><li><a class="underline" href="#<?= esc((string) $key, 'attr') ?>"><?= esc($error) ?></a></li><?php endforeach ?></ul></div></div><?php endif ?>

<div class="form-layout"><form method="post" action="<?= site_url($editing ? 'usuarios/' . $account['id'] : 'usuarios') ?>" class="card card-border form-card bg-base-100"><div class="card-body"><?= csrf_field() ?><fieldset class="fieldset"><legend class="fieldset-legend text-lg">Datos de acceso</legend><div class="form-grid">
    <label class="fieldset"><span class="fieldset-legend">Nombre de usuario</span><input id="username" class="input w-full" name="username" value="<?= esc($value('username'), 'attr') ?>" required maxlength="30" autocomplete="username"><span class="label">Letras, números, punto, guion o guion bajo.</span></label>
    <label class="fieldset"><span class="fieldset-legend">Correo electrónico</span><input id="email" class="input w-full" type="email" name="email" value="<?= esc($value('email'), 'attr') ?>" required maxlength="254" autocomplete="email"></label>
    <?php if (!$editing): ?><label class="fieldset"><span class="fieldset-legend">Contraseña temporal</span><input id="password" class="input w-full" type="password" name="password" required minlength="12" autocomplete="new-password"><span class="label">Mínimo 12 caracteres. Entrégala por un canal seguro.</span></label><?php endif ?>
    <label class="fieldset"><span class="fieldset-legend">Función</span><select id="group" class="select w-full" name="group" required><?php foreach ($groups as $key => $group): ?><option value="<?= esc($key, 'attr') ?>" <?= $value('group', 'cajera') === $key ? 'selected' : '' ?>><?= esc($group['title']) ?></option><?php endforeach ?></select></label>
</div></fieldset><div class="form-actions"><a class="btn" href="<?= site_url('usuarios') ?>">Cancelar</a><button class="btn btn-primary" type="submit">Guardar usuario</button></div></div></form>
<aside class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Acceso individual</h2><p>Cada persona debe usar su propia cuenta. La función determina los permisos base y puede cambiarse después.</p></div></aside></div>

<?php if ($editing): ?>
<form id="permissions" method="post" action="<?= site_url('usuarios/' . $account['id'] . '/permisos') ?>" class="card card-border mt-8 bg-base-100">
    <div class="card-body"><?= csrf_field() ?><fieldset class="fieldset"><legend class="fieldset-legend text-lg">Permisos individuales</legend><p class="mb-3 text-sm text-base-content/70">Amplían el acceso heredado de la función. Deja todo sin marcar si no necesita excepciones.</p><div class="grid gap-3 md:grid-cols-2"><?php foreach ($availablePermissions as $key => $label): ?><label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-field border border-base-300 p-3"><input class="checkbox mt-0.5" type="checkbox" name="permissions[]" value="<?= esc($key, 'attr') ?>" <?= in_array($key, $account['permissions'], true) ? 'checked' : '' ?>><span><strong class="block font-medium"><?= esc($label) ?></strong><span class="font-data text-xs text-base-content/70"><?= esc($key) ?></span></span></label><?php endforeach ?></div></fieldset><div class="card-actions mt-3 justify-end"><button class="btn" type="submit">Guardar permisos</button></div></div>
</form>
<?php endif ?>

<?php if ($editing): ?><form method="post" action="<?= site_url('usuarios/' . $account['id'] . '/estado') ?>" class="card card-border mt-8 bg-base-100" data-confirm="<?= $account['active'] ? 'La persona no podrá ingresar hasta que reactives su cuenta.' : 'La persona recuperará el acceso según su función asignada.' ?>"><div class="card-body flex-row flex-wrap items-center justify-between gap-4"><?= csrf_field() ?><input type="hidden" name="active" value="<?= $account['active'] ? '0' : '1' ?>"><div><h2 class="card-title">Estado de la cuenta</h2><p class="mt-1 text-sm text-base-content/70"><?= $account['active'] ? 'La cuenta está activa.' : 'La cuenta está restringida.' ?></p></div><button class="btn <?= $account['active'] ? 'btn-error btn-soft' : '' ?>" type="submit"><?= $account['active'] ? 'Desactivar usuario' : 'Reactivar usuario' ?></button></div></form><?php endif ?>

<?= $this->endSection() ?>
