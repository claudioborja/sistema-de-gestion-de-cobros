<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'showDescription' => true,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $application['cliente'], 'url' => site_url('clientes/' . (int) $application['cliente_id'] . '/pagos')],
        ['label' => 'Cobro #' . (int) $application['pago_id'], 'url' => site_url('pagos/' . (int) $application['pago_id'])],
        ['label' => 'Revertir aplicación'],
    ],
], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div class="alert alert-error mb-5" role="alert" tabindex="-1" aria-labelledby="application-reversal-errors-title">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <div><h2 id="application-reversal-errors-title" class="font-semibold">Revisa la corrección</h2><p><?= esc(implode(' ', array_unique(array_values($errors)))) ?></p></div>
</div>
<?php endif ?>

<form method="post" action="<?= site_url('aplicaciones/' . (int) $application['id'] . '/revertir') ?>" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <?= csrf_field() ?>
    <input type="hidden" name="idempotency_key" value="<?= esc((string) old('idempotency_key', $idempotencyKey), 'attr') ?>">

    <section class="card card-border bg-base-100">
        <div class="card-body">
            <div><h2 class="card-title">Motivo de la redistribución</h2><p class="mt-1 text-sm text-base-content/70">La aplicación original permanecerá en el historial y su importe volverá al cobro para una nueva distribución.</p></div>
            <div class="alert alert-warning alert-soft mt-2" role="status">
                <i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i>
                <div><p>La cartera volverá a mostrar <span class="font-data font-semibold">$<?= esc($portfolioAfterReversal) ?></span> pendientes.</p><p>El saldo disponible del cobro aumentará a <span class="font-data font-semibold">$<?= esc($availableAfterReversal) ?></span>.</p></div>
            </div>
            <fieldset class="fieldset mt-2">
                <legend class="fieldset-legend">Motivo administrativo</legend>
                <textarea id="application-reversal-reason" class="textarea h-32 w-full <?= isset($errors['reason']) ? 'textarea-error' : '' ?>" name="reason" minlength="10" maxlength="500" required aria-describedby="application-reversal-help<?= isset($errors['reason']) ? ' application-reversal-error' : '' ?>"><?= esc((string) old('reason')) ?></textarea>
                <p id="application-reversal-help" class="label">Explica por qué esta cuota debe dejar de recibir el importe.</p>
                <?php if (isset($errors['reason'])): ?><p id="application-reversal-error" class="label text-error"><?= esc($errors['reason']) ?></p><?php endif ?>
            </fieldset>
            <div class="card-actions mt-2 justify-end">
                <a class="btn" href="<?= site_url('pagos/' . (int) $application['pago_id']) ?>">Cancelar</a>
                <button class="btn btn-error" type="submit" data-confirm="Confirma la reversión de la aplicación #<?= (int) $application['id'] ?> por $<?= esc($application['importe'], 'attr') ?>. El importe volverá a quedar disponible para redistribuir y la cartera se actualizará.">Revertir aplicación</button>
            </div>
        </div>
    </section>

    <aside class="order-first grid content-start gap-5 lg:order-last">
        <section class="card card-border bg-base-100">
            <div class="card-body">
                <h2 class="card-title">Aplicación afectada</h2>
                <dl class="mt-2 grid gap-3 text-sm">
                    <div><dt class="text-base-content/70">Cliente</dt><dd class="font-medium"><?= esc($application['cliente']) ?></dd></div>
                    <div><dt class="text-base-content/70">Cobro</dt><dd class="font-data">#<?= (int) $application['pago_id'] ?></dd></div>
                    <div><dt class="text-base-content/70">Documento</dt><dd class="font-data"><?= esc($application['numero_completo'] ?: $application['tipo']) ?></dd></div>
                    <div><dt class="text-base-content/70">Cuota</dt><dd class="font-data"><?= (int) $application['numero_cuota'] ?></dd></div>
                    <div><dt class="text-base-content/70">Importe aplicado</dt><dd class="font-data font-semibold">$<?= esc($application['importe']) ?></dd></div>
                    <div><dt class="text-base-content/70">Estado actual</dt><dd><span class="badge badge-success badge-soft">VIGENTE</span></dd></div>
                </dl>
            </div>
        </section>
    </aside>
</form>
<?= $this->endSection() ?>
