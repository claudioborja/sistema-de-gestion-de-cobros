<?= $this->extend('layout') ?><?= $this->section('content') ?>
<?php $errors=session('errors') ?? []; $value=static fn($key,$default='')=>old($key,$item[$key] ?? $default); ?>
<a class="inline-flex items-center gap-2 text-sm mb-6" href="<?= site_url('catalogo') ?>"><i data-lucide="chevron-left" class="icon" aria-hidden="true"></i>Volver al catálogo</a><h1 class="text-3xl font-semibold mb-7"><?= esc($title) ?></h1>
<?php if ($errors): ?><div class="alert alert-error mb-5" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form method="post" action="<?= site_url(isset($item['id'])?'catalogo/'.$item['id']:'catalogo') ?>" class="panel p-6 md:p-8 max-w-3xl">
<?= csrf_field() ?><input type="hidden" name="version" value="<?= $value('version','1') ?>">
<div class="grid gap-5 sm:grid-cols-2">
<?php foreach (['nombre'=>'Nombre *','codigo'=>'Código *','precio_referencia'=>'Precio de referencia (USD)'] as $key=>$label): ?><label class="field" for="<?= $key ?>"><?= $label ?><input class="input" id="<?= $key ?>" name="<?= $key ?>" value="<?= $value($key) ?>" <?= $key!=='precio_referencia'?'required':'inputmode="decimal" placeholder="0.00"' ?>><?php if (isset($errors[$key])): ?><span class="field-error"><?= esc($errors[$key]) ?></span><?php endif ?></label><?php endforeach ?>
<label class="field" for="tipo">Tipo<select class="select w-full" id="tipo" name="tipo"><option value="SERVICIO" <?= $value('tipo')==='SERVICIO'?'selected':'' ?>>Servicio</option><option value="PRODUCTO" <?= $value('tipo')==='PRODUCTO'?'selected':'' ?>>Producto</option></select></label>
<?php if (isset($item['id'])): ?><label class="field" for="activo">Estado<select class="select w-full" name="activo" id="activo"><option value="1" <?= $value('activo')==='1'?'selected':'' ?>>Activo</option><option value="0" <?= $value('activo')==='0'?'selected':'' ?>>Inactivo</option></select></label><?php endif ?></div>
<p class="mt-5 text-sm text-base-content/65">El precio es una referencia. No representa una deuda ni un cobro.</p>
<div class="flex gap-3 mt-8 border-t border-base-300 pt-6"><button type="submit" class="btn btn-primary">Guardar ítem</button><a class="btn btn-ghost" href="<?= site_url('catalogo') ?>">Cancelar</a></div></form>
<?= $this->endSection() ?>
