<?php
$clientBase = 'clientes/' . (int) $client['id'];
$clientPath = trim(uri_string(), '/');
$clientLinks = [
    ['expediente', 'Expediente', 'users', 'clientes.ver'],
    ['suscripciones', 'Suscripciones', 'calendar-clock', 'contratos.ver'],
    ['documentos', 'Documentos', 'file-text', 'cartera.ver'],
    ['cartera', 'Cartera', 'wallet-minimal', 'cartera.ver'],
    ['pagos', 'Cobros', 'banknote', 'pagos.ver'],
    ['estado-cuenta', 'Estado de cuenta', 'file-bar-chart', 'cartera.ver'],
    ['credito', 'Crédito', 'badge-dollar-sign', 'clientes.ver'],
];
?>
<section class="mb-6 rounded-box border border-base-300 bg-base-100 p-4" aria-label="Cliente de la operación">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div><p class="font-semibold"><?= esc($client['nombre']) ?></p><p class="font-data text-sm text-base-content/70">#<?= (int) $client['id'] ?> · <?= esc($client['identificacion'] ?: 'Sin identificación') ?></p></div>
        <a class="btn btn-sm" href="<?= site_url('clientes') ?>">Cambiar cliente</a>
    </div>
    <p id="client-navigation-hint" class="mb-2 text-xs text-base-content/70 md:hidden">Desliza horizontalmente para ver todas las secciones.</p>
    <nav class="overflow-x-auto pb-2 md:pb-0" aria-label="Expediente del cliente" aria-describedby="client-navigation-hint">
        <div class="tabs tabs-border w-max min-w-full">
        <?php foreach ($clientLinks as [$suffix, $label, $icon, $permission]): ?>
            <?php if (!\App\Services\Access::can((int) auth()->id(), $permission)) continue; ?>
            <?php $selected = ($activeSection ?? '') === $suffix || $clientPath === $clientBase . '/' . $suffix || str_starts_with($clientPath, $clientBase . '/' . $suffix . '/'); ?>
            <a class="tab gap-2 <?= $selected ? 'tab-active' : '' ?>" href="<?= site_url($clientBase . '/' . $suffix) ?>" <?= $selected ? 'aria-current="page"' : '' ?>><i data-lucide="<?= esc($icon, 'attr') ?>" class="icon" aria-hidden="true"></i><?= esc($label) ?></a>
        <?php endforeach ?>
        </div>
    </nav>
</section>
