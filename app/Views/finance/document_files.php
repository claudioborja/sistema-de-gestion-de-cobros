<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $document['cliente'], 'url' => site_url('clientes/' . (int) $document['cliente_id'] . '/documentos')],
        ['label' => 'Documento #' . (int) $document['id'], 'url' => site_url('documentos/' . (int) $document['id'])],
        ['label' => 'Archivos'],
    ],
], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div class="alert alert-error mb-5" role="alert">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <span><?= esc(implode(' ', array_unique(array_values($errors)))) ?></span>
</div>
<?php endif ?>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="grid content-start gap-5">
        <section class="card card-border bg-base-100">
            <div class="card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="card-title"><?= esc($document['tipo']) ?> <?= esc($document['numero_completo'] ?: '#' . $document['id']) ?></h2>
                        <p class="mt-1 text-sm text-base-content/70"><?= esc($document['cliente']) ?> · <?= esc($document['concepto']) ?></p>
                    </div>
                    <span class="badge badge-soft"><?= count($files) ?> <?= count($files) === 1 ? 'archivo' : 'archivos' ?></span>
                </div>
            </div>
        </section>

        <?php if (!$files): ?>
            <?= view('partials/empty_state', [
                'icon' => 'folder-open',
                'title' => 'Sin archivos adjuntos',
                'description' => $canManage
                    ? 'Carga el primer respaldo documental desde el formulario.'
                    : 'Este documento todavía no tiene respaldos disponibles.',
            ], ['saveData' => false]) ?>
        <?php else: ?>
        <section class="card card-border bg-base-100">
            <div class="card-body p-0">
                <div class="hidden overflow-x-auto md:block">
                    <table class="table">
                        <caption class="sr-only">Archivos privados vinculados al documento</caption>
                        <thead><tr><th>Archivo</th><th>Tipo y tamaño</th><th>Cargado por</th><th>Fecha</th><th><span class="sr-only">Acción</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td><span class="block max-w-64 truncate font-medium" title="<?= esc($file['nombre_original'], 'attr') ?>"><?= esc($file['nombre_original']) ?></span><?php if ($file['notas']): ?><span class="mt-1 block max-w-80 text-sm text-base-content/70"><?= esc($file['notas']) ?></span><?php endif ?></td>
                                <td><?= esc($file['tipo_legible']) ?><span class="font-data block text-sm text-base-content/70"><?= esc($file['tamano_legible']) ?></span></td>
                                <td><?= esc($file['cargado_por_nombre']) ?></td>
                                <td class="font-data whitespace-nowrap"><?= esc($file['cargado_en_legible']) ?></td>
                                <td class="text-right"><a class="btn btn-sm" href="<?= site_url('archivos/' . (int) $file['id'] . '/descargar') ?>"><i data-lucide="download" class="icon" aria-hidden="true"></i>Descargar</a></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <ul class="divide-y divide-base-300 md:hidden" aria-label="Archivos privados vinculados al documento">
                <?php foreach ($files as $file): ?>
                    <li class="p-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <i data-lucide="file-text" class="icon mt-1 shrink-0 text-primary" aria-hidden="true"></i>
                            <div class="min-w-0 grow">
                                <p class="break-words font-medium"><?= esc($file['nombre_original']) ?></p>
                                <p class="mt-1 text-sm text-base-content/70"><?= esc($file['tipo_legible']) ?> · <?= esc($file['tamano_legible']) ?></p>
                                <p class="font-data mt-1 text-sm text-base-content/70"><?= esc($file['cargado_en_legible']) ?> · <?= esc($file['cargado_por_nombre']) ?></p>
                                <?php if ($file['notas']): ?><p class="mt-2 text-sm"><?= esc($file['notas']) ?></p><?php endif ?>
                            </div>
                        </div>
                        <a class="btn mt-3 min-h-11 w-full" href="<?= site_url('archivos/' . (int) $file['id'] . '/descargar') ?>"><i data-lucide="download" class="icon" aria-hidden="true"></i>Descargar</a>
                    </li>
                <?php endforeach ?>
                </ul>
            </div>
        </section>
        <?php endif ?>
    </div>

    <aside class="grid content-start gap-5">
        <?php if ($canManage): ?>
        <section class="card card-border bg-base-100">
            <div class="card-body">
                <h2 class="card-title">Adjuntar respaldo</h2>
                <p class="text-sm text-base-content/70">El archivo se guardará fuera del directorio público.</p>
                <form class="mt-3 grid gap-4" method="post" enctype="multipart/form-data" action="<?= site_url('documentos/' . (int) $document['id'] . '/archivos') ?>">
                    <?= csrf_field() ?>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Archivo</legend>
                        <input class="file-input w-full <?= isset($errors['attachment']) ? 'file-input-error' : '' ?>" type="file" name="attachment" accept="application/pdf,image/png,image/jpeg,.pdf,.png,.jpg,.jpeg" aria-describedby="attachment-help<?= isset($errors['attachment']) ? ' attachment-error' : '' ?>" required>
                        <p id="attachment-help" class="label">PDF, PNG o JPEG. Máximo 2 MB.</p>
                        <?php if (isset($errors['attachment'])): ?><p id="attachment-error" class="text-sm text-error"><?= esc($errors['attachment']) ?></p><?php endif ?>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">Notas</legend>
                        <textarea class="textarea h-24 w-full" name="notes" maxlength="500" placeholder="Referencia interna opcional"><?= esc((string) old('notes')) ?></textarea>
                    </fieldset>
                    <button class="btn btn-primary min-h-11" type="submit"><i data-lucide="paperclip" class="icon" aria-hidden="true"></i>Adjuntar archivo</button>
                </form>
            </div>
        </section>
        <?php endif ?>
        <div class="alert alert-info alert-soft" role="note">
            <i data-lucide="shield-check" class="icon" aria-hidden="true"></i>
            <span>Las descargas requieren una sesión activa y permiso sobre archivos privados.</span>
        </div>
        <a class="btn min-h-11" href="<?= site_url('documentos/' . (int) $document['id']) ?>"><i data-lucide="arrow-left" class="icon" aria-hidden="true"></i>Volver al documento</a>
    </aside>
</div>
<?= $this->endSection() ?>
