<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$base = 'documentos/' . (int) $document['id'];
$value = static function (string $name, mixed $fallback = ''): string {
    $input = old($name, $fallback, false);
    return is_scalar($input) ? (string) $input : '';
};
$storedLines = array_map(static fn (array $line): array => [
    'description' => $line['descripcion_pactada'], 'quantity' => $line['cantidad'],
    'amount' => $line['importe_linea_documentado'], 'item_id' => $line['item_id'],
], $document['detalles']);
$oldLines = old('lines', null, false);
$lineValues = is_array($oldLines) ? array_values($oldLines) : $storedLines;
if ($lineValues === []) {
    $lineValues[] = ['description' => '', 'quantity' => '1', 'amount' => ''];
}
?>
<?= view('partials/page_header', [
    'title' => $title, 'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $document['cliente'], 'url' => site_url('clientes/' . (int) $document['cliente_id'] . '/documentos')],
        ['label' => 'Documento #' . (int) $document['id'], 'url' => site_url($base)],
        ['label' => 'Editar borrador'],
    ],
], ['saveData' => false]) ?>
<p class="mb-4"><span class="font-semibold"><?= esc($document['cliente']) ?></span> · Borrador #<?= (int) $document['id'] ?></p>
<?php if ($errors): ?><div class="alert alert-error mb-5" role="alert"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><span><?= esc(implode(' ', array_unique(array_values($errors)))) ?></span></div><?php endif ?>
<?php if (isset($errors['draft_revision'])): ?><p class="mb-4"><a class="link" href="<?= site_url($base) ?>">Revisar la versión guardada del documento</a> antes de volver a editar.</p><?php endif ?>
<form method="post" action="<?= site_url($base) ?>" class="grid gap-5">
    <?= csrf_field() ?>
    <input type="hidden" name="idempotency_key" value="<?= esc($value('idempotency_key', $idempotencyKey), 'attr') ?>">
    <input type="hidden" name="draft_revision" value="<?= esc($value('draft_revision', $document['draft_revision']), 'attr') ?>">
    <section class="card card-border bg-base-100">
        <div class="card-body grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><h2 class="card-title">Encabezado del documento</h2><p class="mt-1 text-sm text-base-content/70">Guardar el borrador actualiza su total; todavía no genera deuda.</p></div>
            <label class="fieldset"><span class="fieldset-legend">Tipo</span><select class="select w-full" name="type" required <?= isset($errors['type']) ? 'aria-invalid="true"' : '' ?>><?php foreach (['FACTURA' => 'Factura', 'NOTA_VENTA' => 'Nota de venta', 'OTRO' => 'Otro'] as $type => $label): ?><option value="<?= esc($type, 'attr') ?>" <?= $value('type', $document['tipo']) === $type ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select><?php if (isset($errors['type'])): ?><span class="text-error"><?= esc($errors['type']) ?></span><?php endif ?></label>
            <label class="fieldset"><span class="fieldset-legend">Número</span><input class="input w-full" name="number" maxlength="100" value="<?= esc($value('number', $document['numero_completo']), 'attr') ?>" <?= isset($errors['number']) ? 'aria-invalid="true"' : '' ?>><span class="label">Opcional; no puede repetirse para el mismo tipo.</span><?php if (isset($errors['number'])): ?><span class="text-error"><?= esc($errors['number']) ?></span><?php endif ?></label>
            <label class="fieldset"><span class="fieldset-legend">Fecha de emisión</span><input class="input w-full" type="date" name="issued_on" value="<?= esc($value('issued_on', $document['fecha_emision']), 'attr') ?>" required <?= isset($errors['issued_on']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($errors['issued_on'])): ?><span class="text-error"><?= esc($errors['issued_on']) ?></span><?php endif ?></label>
            <label class="fieldset"><span class="fieldset-legend">Concepto</span><textarea class="textarea h-24 w-full" name="concept" maxlength="300" required <?= isset($errors['concept']) ? 'aria-invalid="true"' : '' ?>><?= esc($value('concept', $document['concepto'])) ?></textarea><?php if (isset($errors['concept'])): ?><span class="text-error"><?= esc($errors['concept']) ?></span><?php endif ?></label>
        </div>
    </section>
    <section class="card card-border bg-base-100">
        <div class="card-body">
            <div><h2 class="card-title">Líneas documentadas</h2><p class="mt-1 text-sm text-base-content/70">El importe corresponde al total de cada línea. Las filas vacías no se guardan.</p></div>
            <?php if (isset($errors['lines'])): ?><p class="text-error"><?= esc($errors['lines']) ?></p><?php endif ?>
            <div class="mt-3 grid gap-4" data-document-lines>
                <?php foreach ($lineValues as $index => $line): $line = is_array($line) ? $line : []; ?>
                <fieldset class="fieldset rounded-box border border-base-300 p-4" data-document-line>
                    <legend class="fieldset-legend"><span data-line-title>Línea <?= $index + 1 ?></span></legend>
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_9rem_10rem]">
                        <?php foreach (['description' => 'Descripción', 'quantity' => 'Cantidad', 'amount' => 'Total de línea USD'] as $field => $label):
                            $fieldValue = $line[$field] ?? ($field === 'quantity' ? '1' : '');
                            $fieldValue = is_scalar($fieldValue) ? (string) $fieldValue : '';
                            $fieldError = $errors['lines.' . $index . '.' . $field] ?? null;
                        ?>
                        <label class="fieldset"><span class="fieldset-legend"><?= esc($label) ?></span><input class="input w-full" name="lines[<?= $index ?>][<?= $field ?>]" value="<?= esc($fieldValue, 'attr') ?>" <?= $field === 'description' ? 'maxlength="300"' : ($field === 'quantity' ? 'type="number" min="0.0001" step="0.0001"' : 'type="number" min="0.01" step="0.01"') ?> <?= $index === 0 ? 'required' : '' ?> <?= $fieldError ? 'aria-invalid="true"' : '' ?>><?php if ($fieldError): ?><span class="text-error"><?= esc($fieldError) ?></span><?php endif ?></label>
                        <?php endforeach ?>
                    </div>
                    <?php if (isset($line['item_id']) && is_scalar($line['item_id'])): ?><input type="hidden" name="lines[<?= $index ?>][item_id]" value="<?= esc((string) $line['item_id'], 'attr') ?>"><?php endif ?>
                    <button type="button" class="btn btn-ghost justify-self-end" data-remove-line <?= count($lineValues) === 1 ? 'disabled' : '' ?>>Quitar ítem</button>
                </fieldset>
                <?php endforeach ?>
            </div>
            <div><button type="button" class="btn" data-add-line><i data-lucide="plus" class="icon" aria-hidden="true"></i>Agregar ítem</button></div>
            <p class="text-sm" role="status" aria-live="polite" data-lines-status></p>
            <div class="card-actions mt-4 justify-end"><a class="btn" href="<?= site_url($base) ?>">Cancelar</a><button class="btn btn-primary" type="submit">Guardar cambios</button></div>
        </div>
    </section>
</form>
<?= $this->endSection() ?>
