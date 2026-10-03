<?php
$errors = session('errors') ?? [];
$value = static fn (string $key, string $default = '') => old($key, $client[$key] ?? $default);
$editing = isset($client['id']);
$autoOpen = $autoOpen ?? false;
?>

<dialog id="client-form-modal" class="modal modal-bottom sm:modal-middle<?= $autoOpen ? ' modal-open' : '' ?>" aria-labelledby="client-form-title" aria-describedby="client-form-description" data-close-url="<?= site_url('clientes') ?>"<?= $autoOpen ? ' data-auto-open="true"' : '' ?>>
    <div class="modal-box max-h-[calc(100dvh-2rem)] w-full max-w-4xl overflow-y-auto p-0">
        <header class="flex items-start justify-between gap-4 border-b border-base-300 px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 id="client-form-title" class="text-xl font-semibold"><?= esc($title) ?></h2>
                <p id="client-form-description" class="mt-1 text-sm text-base-content/70"><?= esc($description) ?></p>
            </div>
            <a class="btn btn-ghost btn-square shrink-0" href="<?= site_url('clientes') ?>" aria-label="Cerrar formulario">
                <i data-lucide="x" class="icon" aria-hidden="true"></i>
            </a>
        </header>

        <form id="client-editor-form" method="post" action="<?= site_url($editing ? 'clientes/' . $client['id'] : 'clientes') ?>">
            <div class="space-y-5 px-5 py-5 sm:px-6">
            <?= csrf_field() ?>
            <input type="hidden" name="version" value="<?= esc($value('version', '1'), 'attr') ?>">

            <?php if ($errors): ?>
            <div id="form-errors" class="alert alert-error" role="alert" tabindex="-1" aria-labelledby="form-errors-title">
                <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
                <div><h3 id="form-errors-title" class="font-semibold">No se guardaron los cambios</h3><ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $key => $error): ?><li><a class="underline" href="#<?= esc((string) $key, 'attr') ?>"><?= esc($error) ?></a></li><?php endforeach ?></ul></div>
            </div>
            <?php endif ?>

            <fieldset class="fieldset p-0">
                <legend class="fieldset-legend text-base">Datos del cliente</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach (['nombre' => ['Nombre o razón social', 'text', 160], 'direccion' => ['Dirección', 'text', 240], 'email' => ['Correo electrónico', 'email', 190], 'telefono' => ['Teléfono', 'tel', 30]] as $key => [$label, $type, $max]): ?>
                    <label class="form-field" for="<?= $key ?>">
                        <span><?= $label ?><?= $key === 'nombre' ? ' *' : '' ?></span>
                        <input class="input w-full <?= isset($errors[$key]) ? 'input-error' : '' ?>" id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" maxlength="<?= $max ?>" value="<?= esc($value($key), 'attr') ?>" autocomplete="<?= $key === 'email' ? 'email' : ($key === 'telefono' ? 'tel' : 'off') ?>" <?= $key === 'nombre' ? 'required autofocus' : '' ?> <?= isset($errors[$key]) ? 'aria-invalid="true" aria-describedby="' . $key . '-error"' : '' ?>>
                        <?php if (isset($errors[$key])): ?><span class="field-error" id="<?= $key ?>-error"><?= esc($errors[$key]) ?></span><?php endif ?>
                    </label>
                <?php endforeach ?>
                </div>
            </fieldset>

            <fieldset class="fieldset p-0">
                <legend class="fieldset-legend text-base">Identificación</legend>
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_8rem]">
                    <label class="form-field" for="tipo">
                        <span>Tipo de identificación</span>
                        <select class="select w-full" id="tipo" name="tipo" autocomplete="off"><?php foreach (['CEDULA' => 'Cédula', 'RUC' => 'RUC', 'PASAPORTE' => 'Pasaporte', 'OTRO' => 'Otro'] as $key => $label): ?><option value="<?= $key ?>" <?= html_entity_decode($value('tipo', 'CEDULA')) === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select>
                    </label>
                    <label class="form-field" for="identificacion">
                        <span>Número de identificación</span>
                        <input class="input w-full <?= isset($errors['identificacion']) ? 'input-error' : '' ?>" id="identificacion" name="identificacion" maxlength="40" value="<?= esc($value('identificacion'), 'attr') ?>" autocomplete="off" <?= isset($errors['identificacion']) ? 'aria-invalid="true" aria-describedby="identificacion-error"' : '' ?>>
                        <?php if (isset($errors['identificacion'])): ?><span class="field-error" id="identificacion-error"><?= esc($errors['identificacion']) ?></span><?php endif ?>
                    </label>
                    <label class="form-field" for="pais">
                        <span>País emisor</span>
                        <input class="input w-full uppercase <?= isset($errors['pais']) ? 'input-error' : '' ?>" id="pais" name="pais" maxlength="2" value="<?= esc($value('pais', 'EC'), 'attr') ?>" autocomplete="off" <?= isset($errors['pais']) ? 'aria-invalid="true" aria-describedby="pais-error"' : '' ?>>
                        <?php if (isset($errors['pais'])): ?><span class="field-error" id="pais-error"><?= esc($errors['pais']) ?></span><?php endif ?>
                    </label>
                </div>
            </fieldset>

            <p class="text-sm text-base-content/70">Solo el nombre es obligatorio. Podrás completar los demás datos después.</p>
            </div>
            <div class="modal-action m-0 border-t border-base-300 px-5 py-4 sm:px-6">
                <a class="btn" href="<?= site_url('clientes') ?>">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar cliente</button>
            </div>
        </form>

        <?php if ($editing && $canChangeState): ?>
        <form class="flex flex-wrap items-center justify-between gap-4 border-t border-base-300 bg-base-200/50 px-5 py-4 sm:px-6" method="post" action="<?= site_url('clientes/' . $client['id'] . '/estado') ?>" data-confirm="<?= esc($client['activo'] ? 'El cliente quedará inactivo, pero su historial se conservará.' : 'El cliente volverá a estar disponible para nuevas operaciones.', 'attr') ?>">
            <?= csrf_field() ?><input name="activo" type="hidden" value="<?= $client['activo'] ? '0' : '1' ?>">
            <div><h3 class="font-semibold">Estado: <?= $client['activo'] ? 'activo' : 'inactivo' ?></h3><p class="mt-1 text-sm text-base-content/70">El cambio conserva todo el historial.</p></div>
            <button type="submit" class="btn <?= $client['activo'] ? 'btn-error btn-soft' : '' ?>"><?= $client['activo'] ? 'Desactivar cliente' : 'Reactivar cliente' ?></button>
        </form>
        <?php endif ?>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Cerrar formulario">Cerrar</button></form>
</dialog>
