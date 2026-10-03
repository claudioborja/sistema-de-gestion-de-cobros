<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $document['cliente'], 'url' => site_url('clientes/' . (int) $document['cliente_id'] . '/documentos')],
        ['label' => 'Documento #' . (int) $document['id']],
    ],
], ['saveData' => false]) ?>
<?php if ($errors): ?><div class="alert alert-error mb-5" role="alert"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><span><?= esc(implode(' ', array_unique(array_values($errors)))) ?></span></div><?php endif ?>
<?= view('partials/workflow_steps', [
    'title' => 'Venta a crédito',
    'current' => $document['estado'] === 'BORRADOR' ? 2 : 3,
    'steps' => ['Registrar ítems', 'Definir meses y revisar', 'Confirmar y cobrar'],
    'description' => 'Divide el total de la venta en cuotas mensuales sin intereses.',
], ['saveData' => false]) ?>
<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="grid min-w-0 grid-cols-1 content-start gap-5">
        <section class="card card-border bg-base-100"><div class="card-body">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="card-title"><?= esc($document['tipo']) ?> <?= esc($document['numero_completo'] ?: '#' . $document['id']) ?></h2><p class="mt-1 text-sm text-base-content/70"><?= esc($document['cliente']) ?> · emitido el <?= esc($document['fecha_emision']) ?></p></div><span class="badge <?= $document['estado'] === 'CONFIRMADO' ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= esc($document['estado']) ?></span></div>
            <p class="mt-3"><?= esc($document['concepto']) ?></p>
            <div class="mt-4 grid grid-cols-2 gap-4 rounded-box bg-base-200 p-4"><div><p class="text-sm text-base-content/70">Total documentado</p><p class="font-data text-xl font-semibold">$<?= esc($document['importe_base']) ?></p></div><div><p class="text-sm text-base-content/70">Saldo en cartera</p><p class="font-data text-xl font-semibold">$<?= esc($document['saldo']) ?></p></div></div>
        </div></section>
        <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Detalle</h2><div class="overflow-x-auto"><table class="table"><thead><tr><th>Línea</th><th>Descripción</th><th>Cantidad</th><th>Importe</th></tr></thead><tbody><?php foreach ($document['detalles'] as $line): ?><tr><th><?= (int) $line['renglon'] ?></th><td><?= esc($line['descripcion_pactada']) ?></td><td class="font-data"><?= esc($line['cantidad']) ?></td><td class="font-data">$<?= esc($line['importe_linea_documentado']) ?></td></tr><?php endforeach ?></tbody></table></div></div></section>
        <?php if ($document['cuotas']): ?><section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Cuotas generadas</h2><div class="overflow-x-auto"><table class="table"><thead><tr><th>Cuota</th><th>Vencimiento</th><th>Importe</th></tr></thead><tbody><?php foreach ($document['cuotas'] as $installment): ?><tr><th><?= (int) $installment['numero_cuota'] ?></th><td class="font-data"><?= esc($installment['fecha_vencimiento']) ?></td><td class="font-data">$<?= esc($installment['importe_programado']) ?></td></tr><?php endforeach ?></tbody></table></div></div></section><?php endif ?>
    </div>
    <aside class="grid min-w-0 grid-cols-1 content-start gap-5">
        <?php if ($document['estado'] === 'BORRADOR' && $canConfirm): ?>
        <a class="btn" href="<?= site_url('documentos/' . (int) $document['id'] . '/editar') ?>"><i data-lucide="pencil" class="icon" aria-hidden="true"></i>Editar borrador</a>
        <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Plazo de la venta a crédito</h2><p class="text-sm text-base-content/70">Una cuota por mes, sin intereses. Revisa el calendario antes de generar la deuda.</p>
            <?php if ($creditError): ?><p class="text-error" role="alert"><?= esc($creditError) ?></p><?php endif ?>
            <form method="get" action="<?= site_url('documentos/' . (int) $document['id']) ?>" class="mt-3 grid gap-3">
                <label class="form-field" for="credit-months"><span>Plazo en meses</span><input id="credit-months" class="input w-full" type="number" name="months" min="1" max="120" step="1" value="<?= esc($creditMonths, 'attr') ?>" required aria-describedby="credit-months-help"></label>
                <p id="credit-months-help" class="text-sm text-base-content/70">De 1 a 120 meses; cada cuota debe ser de al menos $0.01.</p>
                <label class="form-field" for="credit-first-due"><span>Fecha de la primera cuota</span><input id="credit-first-due" class="input w-full" type="date" name="first_due_on" min="<?= esc($document['fecha_emision'], 'attr') ?>" value="<?= esc($creditFirstDueOn, 'attr') ?>" required aria-describedby="credit-date-help"></label>
                <p id="credit-date-help" class="text-sm text-base-content/70">Se conserva el día elegido. Si un mes no tiene ese día, vence el último día de ese mes.</p>
                <button class="btn btn-primary" type="submit">Revisar plan de cuotas</button>
            </form>
        </div></section>
        <?php if ($creditPreview): ?>
        <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Vista previa del crédito</h2>
            <p><?= count($creditPreview) ?> cuotas mensuales · Total $<?= esc($document['importe_base']) ?> · Sin intereses.</p>
            <p class="text-sm text-base-content/70">Este plan todavía no genera deuda. Los centavos restantes se distribuyen en las últimas cuotas.</p>
            <div class="max-h-96 overflow-auto"><table class="table table-sm" data-datatable="off"><caption class="sr-only">Calendario mensual propuesto</caption><thead><tr><th scope="col">Cuota</th><th scope="col">Vencimiento</th><th scope="col">USD</th></tr></thead><tbody>
            <?php foreach ($creditPreview as $row): ?><tr><th scope="row"><?= (int) $row['number'] ?></th><td class="font-data whitespace-nowrap"><?= esc($row['due_date']) ?></td><td class="font-data"><?= esc($row['amount']) ?></td></tr><?php endforeach ?>
            </tbody></table></div>
            <form method="post" action="<?= site_url('documentos/' . (int) $document['id'] . '/confirmar') ?>" class="mt-3 grid gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
                <input type="hidden" name="plan_mode" value="monthly">
                <input type="hidden" name="months" value="<?= esc($creditMonths, 'attr') ?>">
                <input type="hidden" name="first_due_on" value="<?= esc($creditFirstDueOn, 'attr') ?>">
                <button class="btn btn-primary" type="submit" data-confirm="Se confirmará la venta con las cuotas de esta vista previa y su total pasará a la cartera.">Confirmar venta en <?= count($creditPreview) ?> cuotas</button>
            </form>
        </div></section>
        <?php endif ?>
        <?php elseif ($document['estado'] === 'CONFIRMADO'): ?>
        <div class="alert alert-success" role="status"><i data-lucide="circle-check" class="icon" aria-hidden="true"></i><span>Documento confirmado. Su saldo ya forma parte de la cartera.</span></div>
        <a class="btn btn-primary" href="<?= site_url('clientes/' . (int) $document['cliente_id'] . '/pagos/nuevo') ?>">Registrar cobro</a>
        <a class="btn" href="<?= site_url('clientes/' . (int) $document['cliente_id'] . '/cartera') ?>">Ver cuotas y saldos</a>
        <?php endif ?>
        <?php if ($canViewAttachments): ?><a class="btn" href="<?= site_url('documentos/' . (int) $document['id'] . '/archivos') ?>"><i data-lucide="paperclip" class="icon" aria-hidden="true"></i>Archivos <span class="badge badge-sm"><?= (int) $attachmentCount ?></span></a><?php endif ?>
        <a class="btn" href="<?= site_url('clientes/' . (int) $document['cliente_id'] . '/documentos') ?>">Volver a documentos</a>
    </aside>
</div>
<?= $this->endSection() ?>
