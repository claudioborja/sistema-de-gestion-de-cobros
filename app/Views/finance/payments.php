<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?php $mode = $mode ?? 'list'; ?>
<?= view('partials/page_header', [
    'context' => 'Pagos',
    'title' => $title,
    'description' => $description,
    'primaryAction' => $mode === 'list' ? ['label' => 'Registrar cobro', 'url' => site_url('pagos/nuevo'), 'icon' => 'plus'] : null,
], ['saveData' => false]) ?>

<?php if ($mode === 'list'): ?>
    <section class="card card-border bg-base-100">
        <div class="card-body">
            <h2 class="card-title">Descargas</h2>
            <div class="card-actions">
                <a class="btn" href="<?= site_url('reportes/pagos.csv') ?>">CSV</a>
                <a class="btn" href="<?= site_url('reportes/pagos.xlsx') ?>">XLSX</a>
            </div>
        </div>
    </section>
<?php endif ?>

<?php if ($mode === 'create'): ?>
    <form method="post" action="<?= site_url('pagos/confirmar') ?>" class="card card-border bg-base-100">
        <div class="card-body">
            <?= csrf_field() ?>
            <label class="form-field"><span>Cliente</span><select class="select w-full" name="cliente" required data-client-select data-source="<?= site_url('clientes/datos') ?>" aria-label="Buscar cliente"><option value="">Escribe al menos 2 caracteres</option></select></label>
            <label class="form-field"><span>Monto</span><input class="input w-full" name="monto" required></label>
            <label class="form-field"><span>Medio</span><select class="select w-full" name="medio"><option>Efectivo</option><option>Transferencia</option></select></label>
            <div class="card-actions justify-end"><button class="btn btn-primary" type="submit" data-confirm="Confirma que el cliente, el monto y el medio de pago son correctos. El cobro quedará registrado en la trazabilidad.">Confirmar pago</button></div>
        </div>
    </form>
<?php elseif ($mode === 'detail'): ?>
    <section class="card card-border bg-base-100">
        <div class="card-body">
            <h2 class="card-title">Pago #<?= (int) $payment['id'] ?></h2>
            <p class="mt-1 text-sm text-base-content/70">Importe: <span class="font-data"><?= esc($payment['monto']) ?></span></p>
            <p class="text-sm">Medio: <?= esc($payment['medio']) ?></p>
            <p class="text-sm">Estado: <?= esc($payment['estado']) ?></p>
            <div class="card-actions mt-3">
                <a class="btn" href="<?= site_url('pagos/' . (int) $payment['id'] . '/revertir') ?>" data-dialog-open="revert-payment">Revertir</a>
            </div>
        </div>
    </section>
    <dialog id="revert-payment" class="modal">
        <div class="modal-box max-w-lg">
            <h2 class="text-xl font-semibold">Revertir pago #<?= (int) $payment['id'] ?></h2>
            <p class="mt-1 text-sm text-base-content/70">La reversión afecta el saldo aplicado y queda registrada para auditoría.</p>
            <form method="post" action="<?= site_url('pagos/' . (int) $payment['id'] . '/revertir') ?>" class="mt-5 grid gap-4">
                <?= csrf_field() ?>
                <label class="form-field"><span>Motivo de reversión</span><textarea class="textarea h-28 w-full" name="motivo" required placeholder="Indica la causa administrativa"></textarea></label>
                <div class="modal-action mt-1"><button class="btn" type="button" data-dialog-close>Cancelar</button><button class="btn btn-error" type="submit">Aplicar reversión</button></div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>Cancelar reversión</button></form>
    </dialog>
    <form class="card card-border bg-base-100" method="post" action="<?= site_url('pagos/' . (int) $payment['id'] . '/aplicaciones') ?>">
        <div class="card-body">
            <?= csrf_field() ?>
            <h3 class="card-title">Aplicar pago</h3>
            <label class="form-field">
                <span>Documento de referencia</span>
                <select class="select w-full" name="documento_id" data-enhanced-select>
                    <option value="3001">Documento #3001</option>
                    <option value="3002">Documento #3002</option>
                    <option value="3003">Documento #3003</option>
                </select>
            </label>
            <label class="form-field">
                <span>Monto aplicado</span>
                <input class="input w-full" name="monto_aplicado" type="number" step="0.01" required>
            </label>
            <div class="card-actions justify-end">
                <button class="btn btn-primary" type="submit">Registrar aplicación</button>
            </div>
        </div>
    </form>
<?php elseif ($mode === 'revert'): ?>
    <form method="post" action="<?= site_url('pagos/' . (int) $paymentId . '/revertir') ?>" class="card card-border bg-base-100">
        <div class="card-body">
            <?= csrf_field() ?>
            <h2 class="card-title">Motivo de reversión</h2>
            <input type="hidden" name="pago_id" value="<?= (int) $paymentId ?>">
            <textarea class="textarea h-28 w-full" name="motivo" required placeholder="Indica la causa administrativa"></textarea>
            <div class="card-actions justify-end"><button class="btn btn-error" type="submit" data-confirm="La reversión afectará el saldo aplicado y quedará registrada para auditoría. Esta acción requiere revisión administrativa.">Aplicar reversión</button></div>
        </div>
    </form>
<?php else: ?>
    <?php if (empty($rows)): ?>
        <?= view('partials/empty_state', ['icon' => 'receipt', 'title' => 'Aún no hay pagos', 'description' => 'Registra un cobro para iniciar la trazabilidad.','action' => ['label' => 'Registrar cobro', 'url' => site_url('pagos/nuevo')]], ['saveData' => false]) ?>
    <?php else: ?>
    <div class="hidden overflow-x-auto rounded-box border border-base-300 bg-base-100 md:block"><table class="table" data-datatable="local"><caption class="sr-only">Pagos registrados</caption><thead><tr><th scope="col">Pago</th><th scope="col">Origen</th><th scope="col">Monto</th><th scope="col">Estado</th><th scope="col">Fecha</th><th scope="col" class="dt-actions">Detalle</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
            <tr><th scope="row" class="font-data">#<?= (int) $row['id'] ?></th><td><?= esc($row['origen']) ?></td><td class="font-data"><?= esc($row['monto']) ?></td><td><span class="badge <?= $row['estado']==='Confirmado' ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= esc($row['estado']) ?></span></td><td class="font-data whitespace-nowrap"><?= esc($row['fecha']) ?></td><td class="text-right"><a class="btn btn-sm" href="<?= site_url('pagos/' . $row['id']) ?>">Abrir</a></td></tr>
        <?php endforeach ?>
    </tbody></table></div>
    <ul class="divide-y divide-base-300 rounded-box border border-base-300 bg-base-100 md:hidden" aria-label="Pagos registrados"><?php foreach ($rows as $row): ?><li class="record-card"><div class="min-w-0"><p class="font-semibold">#<?= (int) $row['id'] ?> · <?= esc($row['origen']) ?></p><p class="font-data mt-1 text-sm text-base-content/70"><?= esc($row['fecha']) ?> · <?= esc($row['monto']) ?></p></div><div class="flex flex-col items-end gap-2"><span class="badge <?= $row['estado']==='Confirmado' ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= esc($row['estado']) ?></span><a class="btn btn-sm" href="<?= site_url('pagos/' . $row['id']) ?>">Abrir</a></div></li><?php endforeach ?></ul>
    <?php endif ?>
<?php endif ?>

<?= $this->endSection() ?>
