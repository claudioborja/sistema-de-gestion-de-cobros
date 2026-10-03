<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?php $mode = $mode ?? 'index'; ?>
<?= view('partials/page_header', [
    'context' => 'Documentación',
    'title' => $title,
    'description' => $description,
    'primaryAction' => $mode === 'index' ? ['label' => 'Nuevo documento', 'url' => site_url('documentos/nuevo'), 'icon' => 'plus'] : null,
], ['saveData' => false]) ?>

<?php if ($mode === 'index'): ?>
<section class="card card-border bg-base-100">
    <div class="card-body p-0">
        <header class="section-heading">
            <div>
                <h2 class="card-title">Documentos registrados</h2>
                <p class="mt-1 text-sm text-base-content/70">Consulta estado, abre detalle o entra al repositorio de archivos.</p>
            </div>
            <a class="btn btn-soft" href="<?= site_url('documentos/nuevo') ?>">Nuevo documento</a>
        </header>
        <?php if (empty($rows)): ?>
            <?= view('partials/empty_state', ['icon' => 'file-text', 'title' => 'Aún no hay documentos', 'description' => 'Crea un borrador para iniciar el control documental.', 'action' => ['label' => 'Nuevo documento', 'url' => site_url('documentos/nuevo')]], ['saveData' => false]) ?>
        <?php else: ?>
        <div class="hidden overflow-x-auto md:block">
            <table class="table" data-datatable="local">
                <thead><tr><th>Documento</th><th>Cliente</th><th>Estado</th><th>Fecha</th><th>Monto</th><th class="dt-actions">Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <th>#<?= $row['id'] ?></th>
                            <td><?= esc($row['cliente']) ?></td>
                            <td><span class="badge <?= $row['estado'] === 'Confirmado' ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= esc($row['estado']) ?></span></td>
                            <td><?= esc($row['fecha']) ?></td>
                            <td class="font-data"><?= esc($row['monto']) ?></td>
                            <td class="flex flex-wrap gap-2">
                                <a class="btn btn-sm" href="<?= site_url('documentos/' . $row['id']) ?>">Ver</a>
                                <a class="btn btn-sm btn-soft" href="<?= site_url('documentos/' . $row['id'] . '/archivos') ?>">Archivos</a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Documentos registrados"><?php foreach ($rows as $row): ?><li class="record-card"><div class="min-w-0"><p class="font-semibold">#<?= (int) $row['id'] ?> · <?= esc($row['cliente']) ?></p><p class="font-data mt-1 text-sm text-base-content/70"><?= esc($row['fecha']) ?> · <?= esc($row['monto']) ?></p></div><div class="flex flex-col items-end gap-2"><span class="badge <?= $row['estado'] === 'Confirmado' ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= esc($row['estado']) ?></span><a class="btn btn-sm" href="<?= site_url('documentos/' . $row['id']) ?>">Abrir</a></div></li><?php endforeach ?></ul>
        <?php endif ?>
    </div>
</section>
<?php endif ?>

<?php if ($mode === 'create'): ?>
<section class="card card-border bg-base-100">
    <div class="card-body">
        <header class="section-heading">
            <div>
                <h2 class="card-title">Nuevo documento</h2>
                <p class="mt-1 text-sm text-base-content/70">Crea un borrador inicial y enlaza evidencia desde el detalle.</p>
            </div>
        </header>
        <form method="post" action="<?= site_url('documentos') ?>" class="mt-4 grid gap-4">
            <?= csrf_field() ?>
            <label class="form-field"><span>Cliente</span><select class="select w-full" name="cliente" required data-client-select data-source="<?= site_url('clientes/datos') ?>" aria-label="Buscar cliente"><option value="">Escribe al menos 2 caracteres</option></select></label>
            <label class="form-field"><span>Monto</span><input class="input w-full" type="number" min="0" step="0.01" name="monto" required placeholder="0.00"></label>
            <label class="form-field"><span>Referencia</span><input class="input w-full" type="text" name="referencia" maxlength="120" placeholder="Referencia interna opcional"></label>
            <label class="form-field"><span>Concepto</span><textarea class="textarea h-24 w-full" name="concepto" placeholder="Detalle corto del documento"></textarea></label>
            <div class="card-actions justify-end gap-2">
                <a class="btn" href="<?= site_url('documentos') ?>">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar borrador</button>
            </div>
        </form>
    </div>
</section>
<?php endif ?>

<?php if ($mode === 'detail'): ?>
<section class="card card-border bg-base-100">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 class="card-title">Documento #<?= $document['id'] ?></h2>
            <div class="flex flex-wrap gap-2">
                <a class="btn" href="<?= site_url('documentos') ?>">Volver</a>
                <a class="btn btn-soft" href="<?= site_url('documentos/' . $document['id'] . '/archivos') ?>">Archivos</a>
            </div>
        </div>
        <form method="post" action="<?= site_url('documentos/' . $document['id']) ?>" class="mt-4 grid gap-4 sm:grid-cols-2">
            <?= csrf_field() ?>
            <label class="form-field"><span>Cliente</span><input class="input w-full" type="text" name="cliente" value="<?= esc($document['cliente'], 'attr') ?>" required></label>
            <label class="form-field"><span>Monto</span><input class="input w-full" type="number" step="0.01" min="0" name="monto" value="<?= esc(str_replace(['$', ',', ' '], '', $document['monto']), 'attr') ?>"></label>
            <label class="form-field"><span>Estado</span><select class="select w-full" name="estado">
                <option value="Borrador" <?= esc($document['estado']) === 'Borrador' ? 'selected' : '' ?>>Borrador</option>
                <option value="Confirmado" <?= esc($document['estado']) === 'Confirmado' ? 'selected' : '' ?>>Confirmado</option>
                <option value="Anulado" <?= esc($document['estado']) === 'Anulado' ? 'selected' : '' ?>>Anulado</option>
            </select></label>
            <label class="form-field"><span>Fecha</span><input class="input w-full" type="date" name="fecha" value="<?= esc($document['fecha']) ?>"></label>
            <label class="form-field sm:col-span-2"><span>Concepto</span><textarea class="textarea h-24 w-full" name="concepto" placeholder="Notas, centro de costo o comentario breve"></textarea></label>
            <div class="sm:col-span-2 card-actions justify-end gap-2">
                <button class="btn btn-soft" type="submit">Guardar cambios</button>
                <a class="btn" href="<?= site_url('documentos/' . $documentId . '/archivos') ?>" data-dialog-open="attach-document-file">Adjuntar archivo</a>
                <button class="btn btn-primary" type="submit" formaction="<?= site_url('documentos/' . $document['id'] . '/confirmar') ?>" data-confirm="Al confirmar, el documento pasará a cartera y sus datos financieros no podrán modificarse directamente.">Confirmar documento</button>
            </div>
        </form>
    </div>
</section>
<dialog id="attach-document-file" class="modal">
    <div class="modal-box max-w-lg">
        <h2 class="text-xl font-semibold">Adjuntar archivo</h2>
        <p class="mt-1 text-sm text-base-content/70">Añade evidencia al documento #<?= (int) $document['id'] ?> sin salir del detalle.</p>
        <form class="mt-5 grid gap-4" method="post" enctype="multipart/form-data" action="<?= site_url('documentos/' . $document['id'] . '/archivos') ?>">
            <?= csrf_field() ?>
            <label class="form-field"><span>Archivo</span><input class="file-input w-full" type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg" required><span class="text-xs text-base-content/60">PDF, PNG o JPG. Máximo recomendado: 10 MB.</span></label>
            <label class="form-field"><span>Notas</span><textarea class="textarea h-20 w-full" name="notas" placeholder="Referencia interna del archivo"></textarea></label>
            <div class="modal-action mt-1"><button class="btn" type="button" data-dialog-close>Cancelar</button><button class="btn btn-primary" type="submit">Adjuntar</button></div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>Cancelar adjunto</button></form>
</dialog>
<?php endif ?>

<?php if ($mode === 'files'): ?>
<section class="card card-border bg-base-100">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 class="card-title">Archivos del documento #<?= $documentId ?></h2>
            <div class="flex gap-2">
                <a class="btn" href="<?= site_url('documentos/' . $documentId) ?>">Ver documento</a>
            </div>
        </div>
        <ul class="mt-4 list">
            <?php if (empty($files)): ?>
                <li class="list-row items-center"><i data-lucide="file-x" class="icon" aria-hidden="true"></i><div class="list-col-grow"><p class="font-medium">Sin archivos</p><p class="text-sm text-base-content/70">Adjunta un nuevo respaldo para poder descargar evidencia.</p></div></li>
            <?php endif ?>
            <?php foreach ($files as $file): ?>
                <li class="list-row items-center">
                    <i data-lucide="file-text" class="icon" aria-hidden="true"></i>
                    <div class="list-col-grow">
                        <p class="font-medium"><?= esc($file['name']) ?></p>
                        <p class="text-sm text-base-content/70"><?= esc($file['type']) ?> · <?= esc($file['size']) ?></p>
                    </div>
                    <a class="btn btn-sm btn-soft" href="<?= site_url('archivos/' . (int) ($file['id'] ?? $documentId) . '/descargar') ?>"><i data-lucide="download" class="icon" aria-hidden="true"></i>Descargar</a>
                </li>
            <?php endforeach ?>
        </ul>
        <form class="mt-4 grid gap-3" method="post" enctype="multipart/form-data" action="<?= site_url('documentos/' . $documentId . '/archivos') ?>">
            <?= csrf_field() ?>
            <label class="form-field"><span>Archivo</span><input class="file-input w-full" type="file" name="attachment" accept=".pdf,.png,.jpg,.jpeg" required><span class="text-xs text-base-content/60">PDF, PNG o JPG. Máximo recomendado: 10 MB.</span></label>
            <label class="form-field"><span>Notas</span><textarea class="textarea h-20 w-full" name="notas" placeholder="Referencia interna del archivo"></textarea></label>
            <div class="card-actions justify-end">
                <button class="btn" type="submit">Adjuntar</button>
            </div>
        </form>
    </div>
</section>
<?php endif ?>

<?= $this->endSection() ?>
