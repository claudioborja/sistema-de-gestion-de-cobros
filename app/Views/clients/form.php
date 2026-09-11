<?= $this->extend('layout') ?><?= $this->section('content') ?>
<?php $errors=session('errors') ?? []; $value=static fn($key,$default='')=>old($key,$client[$key] ?? $default); ?>
<a class="inline-flex items-center gap-2 text-sm mb-6" href="<?= site_url('clientes') ?>"><i data-lucide="chevron-left" class="icon" aria-hidden="true"></i>Volver a clientes</a>
<div class="page-heading"><div><p class="eyebrow mb-2">Directorio</p><h1 class="text-3xl font-semibold"><?= esc($title) ?></h1><p class="mt-2 text-base-content/65">Completa lo que conoces. La identificación y el contacto son opcionales.</p></div></div>
<?php if ($errors): ?><div class="alert alert-error mb-5" role="alert"><div><strong>No se guardaron los cambios.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div></div><?php endif ?>
<form method="post" action="<?= site_url(isset($client['id'])?'clientes/'.$client['id']:'clientes') ?>" class="panel p-6 md:p-8 max-w-4xl">
<?= csrf_field() ?><input type="hidden" name="version" value="<?= $value('version','1') ?>">
<h2 class="text-lg font-semibold mb-6">Datos del cliente</h2>
<div class="grid gap-5 sm:grid-cols-2">
<?php foreach (['nombre'=>['Nombre o razón social *','text',160],'direccion'=>['Dirección','text',240],'identificacion'=>['Número de identificación','text',40],'pais'=>['País emisor (código de dos letras)','text',2],'email'=>['Correo electrónico','email',190],'telefono'=>['Teléfono','tel',30]] as $key=>[$label,$type,$max]): ?>
<label class="field" for="<?= $key ?>"><?= $label ?><input class="input <?= isset($errors[$key])?'input-error':'' ?>" id="<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" maxlength="<?= $max ?>" value="<?= $value($key,$key==='pais'?'EC':'') ?>" <?= $key==='nombre'?'required':'' ?> <?= isset($errors[$key])?'aria-invalid="true" aria-describedby="'.$key.'-error"':'' ?>><?php if (isset($errors[$key])): ?><span class="field-error" id="<?= $key ?>-error"><?= esc($errors[$key]) ?></span><?php endif ?></label>
<?php endforeach ?>
<label class="field" for="tipo">Tipo de identificación<select class="select w-full" id="tipo" name="tipo"><?php foreach (['CEDULA'=>'Cédula','RUC'=>'RUC','PASAPORTE'=>'Pasaporte','OTRO'=>'Otro'] as $key=>$label): ?><option value="<?= $key ?>" <?= html_entity_decode($value('tipo','CEDULA'))===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
</div><div class="mt-8 pt-6 border-t border-base-300 flex gap-3"><button type="submit" class="btn btn-primary">Guardar cliente</button><a class="btn btn-ghost" href="<?= site_url('clientes') ?>">Cancelar</a></div>
</form>
<?php if (isset($client['id']) && \App\Services\Access::can((int)auth()->id(),'clientes.desactivar')): ?><form class="mt-6 panel p-6 max-w-4xl flex flex-wrap justify-between gap-4" method="post" action="<?= site_url('clientes/'.$client['id'].'/estado') ?>"><?= csrf_field() ?><input name="activo" type="hidden" value="<?= $client['activo']?'0':'1' ?>"><div><h2 class="font-semibold">Estado comercial: <?= $client['activo']?'activo':'inactivo' ?></h2><p class="text-sm text-base-content/65">Cambiar el estado conserva todos sus registros.</p></div><button type="submit" class="btn"><?= $client['activo']?'Desactivar cliente':'Reactivar cliente' ?></button></form><?php endif ?>
<?= $this->endSection() ?>
