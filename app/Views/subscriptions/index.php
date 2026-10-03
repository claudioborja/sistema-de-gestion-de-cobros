<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title'=>$title,'description'=>$description], ['saveData'=>false]) ?>
<?php if ($client): ?><?= view('partials/client_navigation',['client'=>$client],['saveData'=>false]) ?><?php endif ?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
<form method="get" class="flex flex-wrap items-end gap-2"><label class="form-field" for="subscription-search"><span>Buscar cliente o servicio</span><input id="subscription-search" class="input" name="q" value="<?= esc($q,'attr') ?>" maxlength="100"></label><button class="btn">Buscar</button></form>
<?php if ($canManage): ?><a class="btn btn-primary" href="<?= site_url($client ? 'clientes/'.$client['id'].'/suscripciones/nueva' : 'suscripciones/nueva') ?>">Nueva suscripción</a><?php endif ?>
</div>
<?php if (!$rows): ?>
<section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">No hay suscripciones<?= $q ? ' con esta búsqueda' : '' ?></h2><p>Registra un servicio del catálogo, por ejemplo Acceso a Moodle, y asigna su precio y calendario al cliente.</p></div></section>
<?php else: ?>
<div class="grid gap-3">
<?php foreach ($rows as $r): ?>
<article class="rounded-box border border-base-300 bg-base-100 p-4 grid gap-3 md:grid-cols-4 md:items-center">
<div><a class="font-semibold underline" href="<?= site_url('suscripciones/'.$r['id']) ?>"><?= esc($r['servicio']) ?></a><p class="text-sm"><?= esc($r['cliente']) ?></p></div>
<div><span class="font-data font-semibold">$<?= esc($r['amount']) ?></span><p class="text-sm"><?= (int)$r['frecuencia_meses']===1 ? 'Mensual' : 'Anual' ?></p></div>
<div><span class="badge badge-outline"><?= esc($r['status']) ?></span><p class="mt-1 text-sm">Desde <?= esc($r['fecha_inicio']) ?></p></div>
<a class="btn justify-self-start md:justify-self-end" href="<?= site_url('suscripciones/'.$r['id']) ?>">Ver períodos y cargos</a>
</article>
<?php endforeach ?>
</div>
<?php endif ?>
<nav class="mt-4 flex flex-wrap items-center gap-3" aria-label="Páginas de suscripciones"><span><?= (int)$total ?> suscripciones · Página <?= (int)$page ?></span>
<?php if ($page>1): ?><a class="btn" href="?<?= esc(http_build_query(['q'=>$q,'page'=>$page-1]),'attr') ?>">Anterior</a><?php endif ?>
<?php if ($page*25<$total): ?><a class="btn" href="?<?= esc(http_build_query(['q'=>$q,'page'=>$page+1]),'attr') ?>">Siguiente</a><?php endif ?></nav>
<?= $this->endSection() ?>
