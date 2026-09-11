<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="page-heading"><div><p class="eyebrow mb-2">Tu espacio de trabajo</p><h1 class="text-3xl font-semibold">Buen día, <?= esc(auth()->user()->username) ?>.</h1><p class="text-base-content/65 mt-2">Organiza la información de tu negocio desde aquí.</p></div></div>
<div class="grid gap-5 sm:grid-cols-2 mb-8">
<div class="panel p-6 border-l-4 border-l-primary"><span class="text-sm text-base-content/65">Clientes registrados</span><p class="text-4xl font-semibold tabular-nums mt-3"><?= $total ?></p><a class="inline-flex items-center gap-2 text-sm mt-4 font-semibold" href="<?= site_url('clientes') ?>">Ver directorio<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></a></div>
<div class="panel p-6"><span class="text-sm text-base-content/65">Clientes activos</span><p class="text-4xl font-semibold tabular-nums mt-3"><?= $active ?></p><p class="text-sm text-base-content/60 mt-4">Disponibles para nuevas operaciones</p></div>
</div>
<section class="panel"><header class="p-6 border-b border-base-300 flex justify-between items-center"><h2 class="font-semibold text-lg">Últimos clientes registrados</h2><span class="badge badge-ghost">Directorio</span></header>
<?php if (!$recent): ?><div class="empty"><i data-lucide="users" class="w-10 h-10 mx-auto mb-4 text-base-content/40" aria-hidden="true"></i><h3 class="text-xl font-semibold">Empieza por tus clientes</h3><p class="text-base-content/65 mt-2">Registra sus datos de contacto para tenerlos a mano.</p><a class="btn mt-6" href="<?= site_url('clientes') ?>">Abrir clientes</a></div>
<?php else: ?><ul class="divide-y divide-base-300"><?php foreach ($recent as $client): ?><li class="px-6 py-4 flex justify-between gap-3"><span class="font-medium"><?= esc($client['nombre']) ?></span><span class="text-sm text-base-content/60">#<?= $client['id'] ?></span></li><?php endforeach ?></ul><?php endif ?></section>
<?= $this->endSection() ?>
