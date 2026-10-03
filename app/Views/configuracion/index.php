<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?php
$methodLabels = ['cash' => 'Efectivo', 'transfer' => 'Transferencia o depósito'];
$methods = array_map(static fn (string $method): string => $methodLabels[$method] ?? $method, $payments['methods'] ?? []);
$messageMethod = 'Correo';
?>

<?= view('partials/page_header', ['context' => 'Administración', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>

<section class="stats stats-vertical w-full border border-base-300 bg-base-100 md:stats-horizontal" aria-label="Resumen de configuración"><div class="stat"><div class="stat-title">Moneda</div><div class="stat-value font-data text-3xl"><?= esc($business['currency'] ?? 'USD') ?></div><div class="stat-desc">Zona <?= esc($business['timezone'] ?? '—') ?></div></div><div class="stat"><div class="stat-title">Medios activos</div><div class="stat-value font-data text-3xl"><?= count($methods) ?></div><div class="stat-desc"><?= esc(implode(' · ', $methods) ?: 'Sin medios') ?></div></div><div class="stat"><div class="stat-title">Canal principal</div><div class="stat-value text-3xl"><?= esc($messageMethod) ?></div><div class="stat-desc">Para comunicaciones de cobro</div></div></section>

<section class="mt-8 grid gap-6 lg:grid-cols-3" aria-label="Áreas de configuración">
    <article class="card card-border bg-base-100"><div class="card-body"><div class="flex items-center justify-between gap-3"><i data-lucide="building-2" class="icon text-primary" aria-hidden="true"></i><span class="badge badge-ghost">Negocio</span></div><h2 class="card-title mt-4">Datos del negocio</h2><dl class="mt-2 space-y-2 text-sm"><div><dt class="text-base-content/70">Nombre</dt><dd class="font-medium"><?= esc($business['business_name'] ?? 'No configurado') ?></dd></div><div><dt class="text-base-content/70">Documento</dt><dd class="font-data"><?= esc(($business['document_type'] ?? '') . ' ' . ($business['document_value'] ?? '')) ?></dd></div></dl><div class="card-actions mt-5"><a class="btn" href="<?= site_url('configuracion/negocio') ?>">Editar negocio<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></a></div></div></article>
    <article class="card card-border bg-base-100"><div class="card-body"><div class="flex items-center justify-between gap-3"><i data-lucide="landmark" class="icon text-primary" aria-hidden="true"></i><span class="badge badge-ghost">Cobros</span></div><h2 class="card-title mt-4">Cuentas bancarias</h2><p class="mt-2 text-sm text-base-content/70"><strong><?= (int) $payments['active_accounts'] ?></strong> cuentas activas para registrar transferencias.</p><div class="card-actions mt-5"><a class="btn" href="<?= site_url('configuracion/pagos') ?>">Administrar cuentas<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></a></div></div></article>
    <article class="card card-border bg-base-100"><div class="card-body"><div class="flex items-center justify-between gap-3"><i data-lucide="messages-square" class="icon text-primary" aria-hidden="true"></i><span class="badge badge-ghost">Mensajes</span></div><h2 class="card-title mt-4">Plantillas de cobro</h2><p class="mt-2 text-sm text-base-content/70">Canal sugerido: <strong><?= esc($messageMethod) ?></strong>.</p><p class="mt-3 line-clamp-3 text-sm text-base-content/70"><?= esc($messages['invoice_subject'] ?? 'Sin asunto configurado') ?></p><div class="card-actions mt-5"><a class="btn" href="<?= site_url('configuracion/mensajes') ?>">Editar mensajes<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></a></div></div></article>
</section>

<?= $this->endSection() ?>
