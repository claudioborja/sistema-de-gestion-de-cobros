<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [['label' => 'Clientes', 'url' => site_url('clientes')], ['label' => $client['nombre'], 'url' => site_url('clientes/' . (int) $client['id'] . '/expediente')], ['label' => 'Cartera']],
], ['saveData' => false]) ?>
<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>
<?php if (!$items): ?>
<?= view('partials/empty_state', ['icon' => 'wallet-minimal', 'title' => 'Sin saldos confirmados', 'description' => 'Los borradores no generan deuda. Confirma un documento para crear sus cuotas.'], ['saveData' => false]) ?>
<?php else: ?>
<section class="card card-border bg-base-100"><div class="card-body p-0"><div class="hidden overflow-x-auto md:block"><table class="table"><caption class="sr-only">Cartera real del cliente</caption><thead><tr><th>Obligación</th><th>Concepto</th><th>Primer vencimiento</th><th>Importe</th><th>Saldo</th><th>Estado</th><th><span class="sr-only">Acción</span></th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><th class="font-data">#<?= (int) $item['id'] ?></th><td><?= esc($item['concepto']) ?></td><td class="font-data"><?= esc($item['vencimiento']) ?></td><td class="font-data">$<?= esc($item['importe_base']) ?></td><td class="font-data font-semibold">$<?= esc($item['saldo']) ?></td><td><span class="badge <?= $item['estado'] === 'VENCIDA' ? 'badge-error badge-soft' : ($item['estado'] === 'RECUPERADA' ? 'badge-success badge-soft' : 'badge-warning badge-soft') ?>"><?= esc($item['estado']) ?></span></td><td class="text-right"><a class="btn btn-sm" href="<?= site_url('obligaciones/' . (int) $item['id']) ?>">Abrir</a></td></tr><?php endforeach ?>
</tbody></table></div><ul class="divide-y divide-base-300 md:hidden" aria-label="Cartera real del cliente"><?php foreach ($items as $item): ?><li class="record-card items-start"><div class="min-w-0 grow"><p class="font-data font-semibold">Obligación #<?= (int) $item['id'] ?></p><p class="mt-1 text-sm"><?= esc($item['concepto']) ?></p><dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-base-content/70">Saldo</dt><dd class="font-data font-semibold">$<?= esc($item['saldo']) ?></dd></div><div><dt class="text-base-content/70">Vencimiento</dt><dd class="font-data"><?= esc($item['vencimiento']) ?></dd></div></dl></div><div class="flex shrink-0 flex-col items-end gap-3"><span class="badge <?= $item['estado'] === 'VENCIDA' ? 'badge-error badge-soft' : ($item['estado'] === 'RECUPERADA' ? 'badge-success badge-soft' : 'badge-warning badge-soft') ?>"><?= esc($item['estado']) ?></span><a class="btn btn-sm" href="<?= site_url('obligaciones/' . (int) $item['id']) ?>">Abrir</a></div></li><?php endforeach ?></ul></div></section>
<?php endif ?>
<?= $this->endSection() ?>
