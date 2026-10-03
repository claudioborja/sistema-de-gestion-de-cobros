<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$base = 'clientes/' . (int) $client['id'] . '/documentos';
$oldLines = old('lines');
$lineValues = is_array($oldLines) ? array_values($oldLines) : [];
if ($lineValues === []) {
    $lineValues[] = ['description' => '', 'quantity' => '1', 'amount' => ''];
}
?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $client['nombre'], 'url' => site_url('clientes/' . (int) $client['id'] . '/expediente')],
        ['label' => $title],
    ],
    'primaryAction' => !$creating && $client['activo'] ? ['label' => 'Nuevo documento', 'url' => site_url($base . '/nuevo'), 'icon' => 'plus'] : null,
], ['saveData' => false]) ?>
<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>

<?php if ($creating): ?>
<?= view('partials/workflow_steps', [
    'title' => 'Venta a crédito', 'current' => 1,
    'steps' => ['Registrar ítems', 'Definir meses y revisar', 'Confirmar y cobrar'],
    'next' => 'Guarda el borrador para elegir el plazo en meses y revisar las cuotas.',
], ['saveData' => false]) ?>
<?php if ($errors): ?><div class="alert alert-error mb-5" role="alert"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><span><?= esc(implode(' ', array_unique(array_values($errors)))) ?></span></div><?php endif ?>
<form method="post" action="<?= site_url($base) ?>" class="grid gap-5">
    <?= csrf_field() ?>
    <input type="hidden" name="cliente_id" value="<?= (int) $client['id'] ?>">
    <input type="hidden" name="idempotency_key" value="<?= esc((string) old('idempotency_key', $idempotencyKey), 'attr') ?>">
    <section class="card card-border bg-base-100">
        <div class="card-body grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><h2 class="card-title">Encabezado del documento</h2><p class="mt-1 text-sm text-base-content/70">El total se obtiene de las líneas; el borrador todavía no afecta la cartera.</p></div>
            <fieldset class="fieldset"><legend class="fieldset-legend">Tipo</legend><select class="select w-full" name="type" required><option value="FACTURA" <?= old('type', 'FACTURA') === 'FACTURA' ? 'selected' : '' ?>>Factura</option><option value="NOTA_VENTA" <?= old('type') === 'NOTA_VENTA' ? 'selected' : '' ?>>Nota de venta</option><option value="OTRO" <?= old('type') === 'OTRO' ? 'selected' : '' ?>>Otro</option></select></fieldset>
            <fieldset class="fieldset"><legend class="fieldset-legend">Número</legend><input class="input w-full" name="number" maxlength="100" value="<?= esc((string) old('number'), 'attr') ?>" placeholder="FAC-001"><p class="label">Opcional; si se informa, no puede repetirse para el mismo tipo.</p></fieldset>
            <fieldset class="fieldset"><legend class="fieldset-legend">Fecha de emisión</legend><input class="input w-full" type="date" name="issued_on" value="<?= esc((string) old('issued_on', date('Y-m-d')), 'attr') ?>" required></fieldset>
            <fieldset class="fieldset"><legend class="fieldset-legend">Concepto</legend><textarea class="textarea h-24 w-full" name="concept" maxlength="300" required><?= esc((string) old('concept')) ?></textarea></fieldset>
        </div>
    </section>
    <section class="card card-border bg-base-100">
        <div class="card-body">
            <div><h2 class="card-title">Líneas documentadas</h2><p class="mt-1 text-sm text-base-content/70">Completa una o varias líneas. Las filas vacías no se guardan.</p></div>
            <div class="mt-3 grid gap-4" data-document-lines>
                <?php foreach ($lineValues as $index => $line): ?>
                <fieldset class="fieldset rounded-box border border-base-300 p-4" data-document-line>
                    <legend class="fieldset-legend"><span data-line-title>Línea <?= $index + 1 ?></span></legend>
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_9rem_10rem]">
                        <label class="fieldset"><span class="fieldset-legend">Descripción</span><input class="input w-full" name="lines[<?= $index ?>][description]" maxlength="300" value="<?= esc((string) ($line['description'] ?? ''), 'attr') ?>" <?= $index === 0 ? 'required' : '' ?>></label>
                        <label class="fieldset"><span class="fieldset-legend">Cantidad</span><input class="input w-full" type="number" name="lines[<?= $index ?>][quantity]" min="0.0001" step="0.0001" value="<?= esc((string) ($line['quantity'] ?? '1'), 'attr') ?>" <?= $index === 0 ? 'required' : '' ?>></label>
                        <label class="fieldset"><span class="fieldset-legend">Importe USD</span><input class="input w-full" type="number" name="lines[<?= $index ?>][amount]" min="0.01" step="0.01" value="<?= esc((string) ($line['amount'] ?? ''), 'attr') ?>" <?= $index === 0 ? 'required' : '' ?>></label>
                    </div>
                    <button type="button" class="btn btn-ghost justify-self-end" data-remove-line <?= count($lineValues) === 1 ? 'disabled' : '' ?>>Quitar ítem</button>
                </fieldset>
                <?php endforeach ?>
            </div>
            <div><button type="button" class="btn" data-add-line><i data-lucide="plus" class="icon" aria-hidden="true"></i>Agregar ítem</button></div>
            <p class="text-sm" role="status" aria-live="polite" data-lines-status></p>
            <div class="card-actions mt-4 justify-end"><a class="btn" href="<?= site_url($base) ?>">Cancelar</a><button class="btn btn-primary" type="submit">Guardar borrador</button></div>
        </div>
    </section>
</form>
<?php else: ?>
<?php if (!$rows): ?>
<?= view('partials/empty_state', ['icon' => 'file-text', 'title' => 'Aún no hay documentos', 'description' => 'Crea un borrador para iniciar la cartera del cliente.', 'action' => $client['activo'] ? ['label' => 'Nuevo documento', 'url' => site_url($base . '/nuevo')] : null], ['saveData' => false]) ?>
<?php else: ?>
<section class="card card-border bg-base-100"><div class="card-body p-0"><div class="hidden overflow-x-auto md:block"><table class="table"><caption class="sr-only">Documentos del cliente</caption><thead><tr><th>Documento</th><th>Concepto</th><th>Emisión</th><th>Total</th><th>Estado</th><th><span class="sr-only">Acción</span></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><th class="font-data">#<?= (int) $row['id'] ?> · <?= esc($row['numero_completo'] ?: $row['tipo']) ?></th><td><?= esc($row['concepto']) ?></td><td class="font-data"><?= esc($row['fecha_origen']) ?></td><td class="font-data">$<?= esc($row['importe_base']) ?></td><td><span class="badge <?= $row['estado'] === 'CONFIRMADO' ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= esc($row['estado']) ?></span></td><td class="text-right"><a class="btn btn-sm" href="<?= site_url('documentos/' . (int) $row['id']) ?>">Abrir</a></td></tr><?php endforeach ?>
</tbody></table></div><ul class="divide-y divide-base-300 md:hidden" aria-label="Documentos del cliente"><?php foreach ($rows as $row): ?><li class="record-card items-start"><div class="min-w-0 grow"><p class="font-data font-semibold">#<?= (int) $row['id'] ?> · <?= esc($row['numero_completo'] ?: $row['tipo']) ?></p><p class="mt-1 text-sm"><?= esc($row['concepto']) ?></p><p class="font-data mt-2 text-sm text-base-content/70"><?= esc($row['fecha_origen']) ?> · $<?= esc($row['importe_base']) ?></p></div><div class="flex shrink-0 flex-col items-end gap-3"><span class="badge <?= $row['estado'] === 'CONFIRMADO' ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= esc($row['estado']) ?></span><a class="btn btn-sm" href="<?= site_url('documentos/' . (int) $row['id']) ?>">Abrir</a></div></li><?php endforeach ?></ul></div></section>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
