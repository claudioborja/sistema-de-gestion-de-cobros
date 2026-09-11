<!doctype html>
<html lang="es" data-theme="light">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= esc($title ?? 'Inicio') ?> · Cobros</title><?= asset_tags() ?></head>
<body class="bg-base-200">
<a href="#contenido" class="sr-only focus:not-sr-only">Saltar al contenido</a>
<div class="app-shell">
<aside class="sidebar">
<a href="<?= site_url() ?>" class="flex items-center gap-3 text-2xl font-bold"><span class="rounded-lg border border-white/20 p-2"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span>Cobros<span class="text-xs font-normal text-white/60">/ gestión</span></a>
<nav aria-label="Navegación principal">
<?php $path=trim(uri_string(),'/'); foreach ([['','Inicio','layout-dashboard','clientes.ver'],['clientes','Clientes','users','clientes.ver'],['catalogo','Catálogo','package','catalogo.gestionar']] as [$url,$label,$icon,$permission]): ?>
<?php if (\App\Services\Access::can((int)auth()->id(),$permission)): ?>
<a class="nav-link" href="<?= site_url($url) ?>" <?= ($path===$url || ($url!=='' && str_starts_with($path,$url.'/'))) ? 'aria-current="page"':'' ?>><i data-lucide="<?= $icon ?>" class="icon" aria-hidden="true"></i><?= $label ?></a>
<?php endif; endforeach ?>
</nav>
<div class="sidebar-footer mt-auto border-t border-white/15 pt-5 text-xs text-white/60 leading-6">Una empresa. Todo en orden.<br>USD · Ecuador</div>
</aside>
<div class="min-w-0">
<header class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 bg-base-100 px-5 py-4 lg:px-10">
<span class="text-sm text-base-content/70"><?= esc((new DateTimeImmutable('now',new DateTimeZone('America/Guayaquil')))->format('d/m/Y')) ?> <span class="mx-2 text-base-content/30">/</span> Gestión de cobranzas</span>
<div class="flex items-center gap-4"><span class="text-sm font-semibold"><?= esc(auth()->user()->username ?? '') ?></span><form method="post" action="<?= site_url('logout') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><i data-lucide="log-out" class="icon" aria-hidden="true"></i>Salir</button></form></div>
</header>
<main id="contenido" class="page">
<?php if ($message=session('message')): ?><div class="alert alert-success mb-6" role="status"><?= esc($message) ?></div><?php endif ?>
<?= $this->renderSection('content') ?>
</main>
</div></div></body></html>
