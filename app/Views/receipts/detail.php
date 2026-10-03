<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $snapshot = $receipt['snapshot']; ?>
<?= view('partials/page_header', ['title' => $title, 'description' => $description, 'breadcrumbs' => [
    ['label' => 'Pagos', 'url' => site_url('pagos')], ['label' => $receipt['code']],
]], ['saveData' => false]) ?>

<?php if ($receipt['payment_status'] === 'REVERTIDO'): ?>
<div class="alert alert-error mb-5" role="status"><i data-lucide="undo-2" class="icon" aria-hidden="true"></i><div><p class="font-semibold">El pago asociado fue revertido.</p><p><?= esc((string) $receipt['reversal_reason']) ?><?php if ($receipt['reversed_at']): ?> · <span class="font-data"><?= esc((string) $receipt['reversed_at']) ?></span><?php endif ?></p></div></div>
<?php endif ?>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
<div class="grid gap-5">
    <section class="card card-border bg-base-100"><div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm text-base-content/70"><?= esc($snapshot['business']['name']) ?></p><h2 class="card-title font-data"><?= esc($receipt['code']) ?></h2></div><span class="badge <?= $receipt['payment_status'] === 'VIGENTE' ? 'badge-success badge-soft' : 'badge-error badge-soft' ?>"><?= esc($receipt['payment_status']) ?></span></div>
        <?php if ($snapshot['business']['identification']): ?><p class="mt-2 text-sm"><?= esc($snapshot['business']['identification']) ?></p><?php endif ?>
        <div class="mt-5 grid gap-4 sm:grid-cols-2"><div><p class="text-sm text-base-content/70">Cliente</p><p class="font-semibold"><?= esc($snapshot['client']['name']) ?></p><p class="font-data text-sm"><?= esc((string) $snapshot['client']['identification']) ?></p></div><div><p class="text-sm text-base-content/70">Emisión</p><p class="font-data"><?= esc($snapshot['issued_at']) ?></p><p class="text-sm">Responsable: <?= esc($snapshot['responsible']) ?></p></div></div>
    </div></section>
    <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Pagos recibidos</h2><div class="mt-3 overflow-x-auto"><table class="table"><thead><tr><th>Medio y referencia</th><th>Fecha</th><th class="text-right">Importe</th></tr></thead><tbody><?php foreach ($snapshot['payments'] as $payment): ?><tr><td><span class="font-semibold"><?= esc($payment['method']) ?></span><p class="font-data text-sm"><?= esc($payment['reference']) ?></p><p class="text-sm text-base-content/70"><?= esc(trim($payment['institution'] . ' · ' . $payment['account_alias'], ' ·')) ?></p></td><td class="font-data"><?= esc($payment['bank_date'] ?: $payment['declared_on']) ?></td><td class="font-data text-right">$<?= esc($payment['amount']) ?></td></tr><?php endforeach ?></tbody></table></div></div></section>
    <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Aplicaciones emitidas</h2>
        <?php if (!$snapshot['applications']): ?><div class="alert mt-3"><i data-lucide="info" class="icon" aria-hidden="true"></i><span>El pago se emitió sin aplicaciones a cuotas.</span></div><?php else: ?><div class="mt-3 overflow-x-auto"><table class="table"><thead><tr><th>Documento</th><th>Cuota</th><th>Concepto</th><th class="text-right">Aplicado</th></tr></thead><tbody><?php foreach ($snapshot['applications'] as $application): ?><tr><td class="font-data"><?= esc($application['document']) ?></td><td><?= (int) $application['installment_number'] ?></td><td><?= esc($application['concept']) ?></td><td class="font-data text-right">$<?= esc($application['amount']) ?></td></tr><?php endforeach ?></tbody></table></div><?php endif ?>
    </div></section>
</div>
<aside class="grid content-start gap-5"><section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Totales emitidos</h2><dl class="mt-2 grid gap-2 text-sm"><div class="flex justify-between"><dt>Recibido</dt><dd class="font-data">$<?= esc($snapshot['total']) ?></dd></div><div class="flex justify-between"><dt>Aplicado</dt><dd class="font-data">$<?= esc($snapshot['applied']) ?></dd></div><div class="flex justify-between border-t border-base-300 pt-2 font-semibold"><dt>Disponible</dt><dd class="font-data">$<?= esc($snapshot['available']) ?></dd></div></dl></div></section><div class="grid gap-2"><a class="btn btn-primary" href="<?= site_url('recibos/' . (int) $receipt['id'] . '/pdf') ?>"><i data-lucide="file-down" class="icon" aria-hidden="true"></i>Descargar copia PDF</a><a class="btn btn-ghost" href="<?= site_url('pagos/' . (int) $snapshot['payments'][0]['id']) ?>">Volver al cobro</a></div><p class="text-sm text-base-content/60">Documento interno. No constituye comprobante tributario.</p></aside>
</div>
<?= $this->endSection() ?>
