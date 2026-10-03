<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$available = (float) $payment['disponible'];
?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $payment['cliente'], 'url' => site_url('clientes/' . (int) $payment['cliente_id'] . '/pagos')],
        ['label' => 'Cobro #' . (int) $payment['id']],
    ],
], ['saveData' => false]) ?>

<?php if ($payment['estado'] === 'REVERTIDO'): ?>
<div class="alert alert-warning mb-5" role="status">
    <i data-lucide="undo-2" class="icon" aria-hidden="true"></i>
    <div><p class="font-semibold">Este cobro fue revertido.</p><p><?= esc($payment['motivo_reversion']) ?><?php if ($payment['fecha_reversion']): ?> · <span class="font-data"><?= esc($payment['fecha_reversion']) ?></span><?php endif ?></p></div>
</div>
<?php endif ?>

<?php if ($errors): ?>
<div class="alert alert-error mb-5" role="alert" tabindex="-1" aria-labelledby="application-errors-title">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <div>
        <h2 id="application-errors-title" class="font-semibold">Revisa la distribución</h2>
        <p><?= esc(implode(' ', array_unique(array_values($errors)))) ?></p>
    </div>
</div>
<?php endif ?>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="grid gap-5">
        <section class="card card-border bg-base-100">
            <div class="card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="card-title">Transferencia <?= esc($payment['referencia']) ?></h2>
                        <p class="mt-1 text-sm text-base-content/70"><?= esc($payment['cliente']) ?> · <?= esc($payment['fecha_bancaria']) ?></p>
                    </div>
                    <span class="badge <?= $payment['estado'] === 'CONFIRMADO' ? 'badge-success badge-soft' : 'badge-error badge-soft' ?>"><?= esc($payment['estado']) ?></span>
                </div>
                <p class="mt-3 text-sm">Cuenta receptora: <?= esc($payment['alias']) ?> · <?= esc($payment['institucion']) ?></p>
            </div>
        </section>

        <section class="card card-border bg-base-100">
            <div class="card-body">
                <h2 class="card-title"><?= $payment['estado'] === 'REVERTIDO' ? 'Aplicaciones históricas' : 'Aplicaciones' ?></h2>
                <?php if (!$payment['aplicaciones']): ?>
                    <div class="alert mt-3" role="status"><i data-lucide="info" class="icon" aria-hidden="true"></i><span>El importe permanece disponible y todavía no se aplicó a cuotas.</span></div>
                <?php else: ?>
                    <div class="mt-3 hidden overflow-x-auto md:block">
                        <table class="table">
                            <caption class="sr-only">Aplicaciones del cobro</caption>
                            <thead><tr><th>Documento</th><th>Cuota</th><th>Concepto</th><th>Importe histórico</th><th>Estado</th><th><span class="sr-only">Acción</span></th></tr></thead>
                            <tbody>
                            <?php foreach ($payment['aplicaciones'] as $application): ?>
                                <tr>
                                    <th class="font-data">#<?= (int) $application['obligacion_id'] ?> · <?= esc($application['numero_completo'] ?: $application['tipo']) ?></th>
                                    <td><?= (int) $application['numero_cuota'] ?></td>
                                    <td><?= esc($application['concepto']) ?><?php if ($application['motivo_reversion']): ?><p class="mt-1 text-sm text-base-content/70"><?= esc($application['motivo_reversion']) ?></p><?php endif ?></td>
                                    <td class="font-data">$<?= esc($application['importe']) ?></td>
                                    <td><span class="badge <?= $application['estado'] === 'VIGENTE' ? 'badge-success badge-soft' : 'badge-error badge-soft' ?>"><?= esc($application['estado']) ?></span></td>
                                    <td class="text-right"><?php if ($canReapply && $application['estado'] === 'VIGENTE'): ?><a class="btn btn-sm" href="<?= site_url('aplicaciones/' . (int) $application['id'] . '/revertir') ?>">Corregir</a><?php endif ?></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <ul class="mt-3 divide-y divide-base-300 md:hidden" aria-label="Aplicaciones del cobro">
                    <?php foreach ($payment['aplicaciones'] as $application): ?>
                        <li class="py-3">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-data font-semibold">Documento #<?= (int) $application['obligacion_id'] ?> · cuota <?= (int) $application['numero_cuota'] ?></p><p class="mt-1 text-sm"><?= esc($application['concepto']) ?></p></div><span class="badge <?= $application['estado'] === 'VIGENTE' ? 'badge-success badge-soft' : 'badge-error badge-soft' ?>"><?= esc($application['estado']) ?></span></div>
                            <p class="font-data mt-2">$<?= esc($application['importe']) ?></p>
                            <?php if ($application['motivo_reversion']): ?><p class="mt-1 text-sm text-base-content/70"><?= esc($application['motivo_reversion']) ?></p><?php endif ?>
                            <?php if ($canReapply && $application['estado'] === 'VIGENTE'): ?><a class="btn btn-sm mt-3" href="<?= site_url('aplicaciones/' . (int) $application['id'] . '/revertir') ?>">Corregir aplicación</a><?php endif ?>
                        </li>
                    <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </section>

        <?php if ($payment['estado'] === 'CONFIRMADO' && $available > 0): ?>
        <section class="card card-border bg-base-100">
            <div class="card-body">
                <div><h2 class="card-title">Aplicar saldo disponible</h2><p class="mt-1 text-sm text-base-content/70"><span class="font-data font-medium">$<?= esc($payment['disponible']) ?> disponibles.</span> Distribuye solo el importe que corresponda; la suma no puede superar ese saldo.</p></div>
                <?php if (!$installments): ?>
                    <div class="alert alert-info alert-soft mt-4" role="status"><i data-lucide="circle-check" class="icon" aria-hidden="true"></i><span>El cliente no tiene cuotas pendientes. El saldo seguirá disponible.</span></div>
                <?php else: ?>
                <form method="post" action="<?= site_url('pagos/' . (int) $payment['id'] . '/aplicaciones') ?>" class="mt-4 grid gap-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="idempotency_key" value="<?= esc((string) old('idempotency_key', $idempotencyKey), 'attr') ?>">
                    <?php foreach ($installments as $index => $installment): ?>
                    <?php $maximum = number_format(min($available, (float) $installment['saldo']), 2, '.', ''); ?>
                    <fieldset class="fieldset rounded-box border border-base-300 p-4">
                        <legend class="fieldset-legend">Documento #<?= (int) $installment['obligacion_id'] ?> · cuota <?= (int) $installment['numero_cuota'] ?></legend>
                        <input type="hidden" name="applications[<?= $index ?>][installment_id]" value="<?= (int) $installment['id'] ?>">
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem] sm:items-end">
                            <div><p class="font-medium"><?= esc($installment['numero_completo'] ?: $installment['tipo']) ?> · <?= esc($installment['concepto']) ?></p><p class="font-data mt-1 text-sm text-base-content/70">Vence <?= esc($installment['fecha_vencimiento']) ?> · saldo $<?= esc($installment['saldo']) ?></p></div>
                            <label class="fieldset"><span class="fieldset-legend">Aplicar USD</span><input class="input w-full <?= isset($errors['applications']) || isset($errors['applications.' . $index . '.amount']) ? 'input-error' : '' ?>" name="applications[<?= $index ?>][amount]" type="number" min="0.01" max="<?= esc($maximum, 'attr') ?>" step="0.01" inputmode="decimal" value="<?= esc((string) old('applications.' . $index . '.amount'), 'attr') ?>" placeholder="0.00" <?= $errors ? 'aria-describedby="error-applications"' : '' ?>></label>
                        </div>
                    </fieldset>
                    <?php endforeach ?>
                    <?php if ($errors): ?><p id="error-applications" class="label text-error"><?= esc($errors['applications'] ?? 'Corrige los importes señalados e inténtalo de nuevo.') ?></p><?php endif ?>
                    <div class="card-actions justify-end"><button class="btn btn-primary" type="submit" data-confirm="Confirma la distribución. Se aplicará como máximo el saldo disponible de $<?= esc($payment['disponible'], 'attr') ?> y la cartera se actualizará inmediatamente.">Confirmar aplicación</button></div>
                </form>
                <?php endif ?>
            </div>
        </section>
        <?php endif ?>
    </div>

    <aside class="grid content-start gap-5">
        <section class="stats stats-vertical w-full border border-base-300 bg-base-100">
            <div class="stat"><div class="stat-title">Importe recibido</div><div class="stat-value font-data text-3xl">$<?= esc($payment['importe']) ?></div></div>
            <div class="stat"><div class="stat-title">Aplicado vigente</div><div class="stat-value font-data text-3xl">$<?= esc($payment['aplicado']) ?></div></div>
            <div class="stat"><div class="stat-title">Disponible</div><div class="stat-value font-data text-3xl">$<?= esc($payment['disponible']) ?></div></div>
        </section>
        <?php if ($receipt): ?><a class="btn btn-primary" href="<?= site_url('recibos/' . (int) $receipt['id']) ?>"><i data-lucide="receipt-text" class="icon" aria-hidden="true"></i>Ver recibo <?= esc($receipt['code']) ?></a><?php endif ?>
        <?php if ($canRevert): ?><a class="btn btn-error" href="<?= site_url('pagos/' . (int) $payment['id'] . '/revertir') ?>"><i data-lucide="undo-2" class="icon" aria-hidden="true"></i>Revertir cobro</a><?php endif ?>
        <a class="btn" href="<?= site_url('clientes/' . (int) $payment['cliente_id'] . '/pagos') ?>">Volver a cobros</a>
        <a class="btn btn-soft" href="<?= site_url('clientes/' . (int) $payment['cliente_id'] . '/cartera') ?>">Ver cartera actualizada</a>
    </aside>
</div>
<?= $this->endSection() ?>
