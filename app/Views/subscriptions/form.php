<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title'=>$title,'description'=>$description], ['saveData'=>false]) ?>
<?= view('partials/client_navigation',['client'=>$client],['saveData'=>false]) ?>
<?php if ($errors): ?><div class="alert alert-error mb-4" role="alert" tabindex="-1" autofocus><ul><?php foreach ($errors as $field=>$e): ?><li><a href="#sub-<?= esc($field,'attr') ?>"><?= esc($e) ?></a></li><?php endforeach ?></ul></div><?php endif ?>
<?php if (!$items || !$client['activo']): ?>
<div class="alert" role="status"><?= !$client['activo'] ? 'Activa el cliente antes de registrar una suscripción.' : 'Primero crea un servicio en el catálogo. Después podrás asignarlo a este cliente.' ?><a class="btn" href="<?= site_url('catalogo/nuevo') ?>">Ir al catálogo</a></div>
<?php else: ?>
<form method="post" action="<?= site_url('clientes/'.$client['id'].'/suscripciones/vista-previa') ?>" class="card card-border bg-base-100"><div class="card-body gap-5">
<?= csrf_field() ?>
<h2 class="card-title">Servicio y condiciones de cobro</h2>
<div class="grid gap-4 md:grid-cols-2">
<?php $selects=[
'item_id'=>['Servicio',array_column($items,'nombre','id')],
'months'=>['Frecuencia',['1'=>'Mensual','12'=>'Anual']],
'policy'=>['Calendario',['ANCLA'=>'Desde la fecha de inicio','PROPORCIONAL'=>'Mes calendario: primer mes proporcional','COMPLETO'=>'Mes calendario: primer mes a precio completo']],
'billing'=>['Momento del cargo',['ANTICIPADO'=>'Al iniciar el período','VENCIDO'=>'Al finalizar el período']],
'acceptance'=>['Renovación',['0'=>'Automática','1'=>'Requiere aceptación de cada período']],
]; ?>
<?php foreach ($selects as $name=>[$label,$options]): ?>
<label class="form-field" for="sub-<?= esc($name) ?>"><span><?= esc($label) ?></span><select id="sub-<?= esc($name) ?>" name="<?= esc($name) ?>" class="select w-full" required aria-describedby="error-<?= esc($name) ?>">
<?php if ($name==='item_id'): ?><option value="">Seleccionar servicio</option><?php endif ?>
<?php foreach ($options as $value=>$labelOption): ?><option value="<?= esc((string)$value,'attr') ?>" <?= (string)$input[$name]===(string)$value ? 'selected' : '' ?>><?= esc($labelOption) ?></option><?php endforeach ?>
</select><span class="text-error text-sm" id="error-<?= esc($name) ?>"><?= esc($errors[$name] ?? '') ?></span></label>
<?php endforeach ?>
<?php foreach (['amount'=>['Precio por período (USD)','number'],'start'=>['Fecha de inicio','date'],'days'=>['Plazo de pago (días)','number'],'end'=>['Final opcional (primer día sin servicio)','date']] as $name=>[$label,$type]): ?>
<label class="form-field" for="sub-<?= esc($name) ?>"><span><?= esc($label) ?></span><input class="input w-full" id="sub-<?= esc($name) ?>" name="<?= esc($name) ?>" type="<?= esc($type) ?>" value="<?= esc($input[$name],'attr') ?>" <?= $name==='end' ? '' : 'required' ?> <?= $name==='amount' ? 'min="0.01" step="0.01"' : ($name==='days' ? 'min="0" max="365" step="1"' : '') ?> aria-describedby="error-<?= esc($name) ?>"><span class="text-error text-sm" id="error-<?= esc($name) ?>"><?= esc($errors[$name] ?? '') ?></span></label>
<?php endforeach ?>
</div>
<p class="text-sm text-base-content/70">La frecuencia anual usa el aniversario de inicio. El final opcional debe coincidir con un límite de período. Las suscripciones generan cuentas por cobrar; el pago se registra por separado.</p>
<button class="btn <?= !$preview ? 'btn-primary' : '' ?> self-start">Revisar próximos períodos</button>
</div></form>
<?php if ($preview): ?>
<section class="card card-border bg-base-100 mt-6"><div class="card-body"><h2 class="card-title">Vista previa antes de activar</h2><p>Estos son los primeros tres períodos del calendario. <?= $input['acceptance']==='1' ? 'Cada período requiere aceptación antes de generar deuda.' : 'Los cargos se generan al llegar su fecha.' ?><?= $input['end'] ? ' Solo se cobrarán los períodos completos anteriores a '.$input['end'].'.' : '' ?></p>
<?= view('subscriptions/period_table',['periods'=>$preview],['saveData'=>false]) ?>
<form method="post" action="<?= site_url('clientes/'.$client['id'].'/suscripciones') ?>">
<?= csrf_field() ?><?php foreach (['item_id','start','end','months','policy','amount','billing','days','acceptance'] as $name): ?><input type="hidden" name="<?= esc($name) ?>" value="<?= esc($input[$name],'attr') ?>"><?php endforeach ?>
<button class="btn btn-primary">Activar suscripción con estas condiciones</button>
</form></div></section>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
