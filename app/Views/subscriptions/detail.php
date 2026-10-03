<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header',['title'=>$title,'description'=>$description],['saveData'=>false]) ?>
<?= view('partials/client_navigation',['client'=>$client,'activeSection'=>'suscripciones'],['saveData'=>false]) ?>
<?php $s=$subscription; $base='suscripciones/'.$s['id']; $latest=$s['conditions'][array_key_last($s['conditions'])]; $previous=static fn(string $form,string $field): string => session('subscription_form')===$form && is_string(old($field)) ? old($field) : '';  ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
<div><span class="badge badge-outline"><?= esc($s['status']) ?></span><span class="ml-2"><?= (int)$s['frecuencia_meses']===1 ? 'Mensual' : 'Anual' ?> · Inicio <?= esc($s['fecha_inicio']) ?></span><?php if ($s['fecha_fin_pactada']): ?><p>Primer día sin servicio: <?= esc($s['fecha_fin_pactada']) ?></p><?php endif ?></div>
<div class="flex flex-wrap gap-2"><?php if ($canGenerate): ?><form method="post" action="<?= site_url($base.'/generar') ?>"><?= csrf_field() ?><button class="btn btn-primary">Generar cargos hasta hoy</button></form><?php endif ?><a class="btn" href="<?= site_url('clientes/'.$client['id'].'/pagos/nuevo') ?>">Registrar pago</a><a class="btn" href="<?= site_url('clientes/'.$client['id'].'/cartera') ?>">Ver cartera</a></div>
</div>
<section class="card card-border bg-base-100 mb-6"><div class="card-body"><h2 class="card-title">Cargos y pagos por período</h2>
<?php if (!$generated): ?><p>Aún no hay cargos. Usa «Generar cargos hasta hoy» cuando corresponda; los períodos futuros se mantienen como proyección.</p><?php else: ?><?= view('subscriptions/period_table',['periods'=>$generated,'showStatus'=>true],['saveData'=>false]) ?><?php endif ?>
</div></section>
<section class="card card-border bg-base-100 mb-6"><div class="card-body"><h2 class="card-title">Proyección de períodos</h2><p class="text-sm">La proyección todavía no es deuda. Los períodos pausados o cancelados no generan cargos. El vencimiento cuenta desde la fecha del cargo, incluso si se genera después.</p>
<?php if ($upcoming): ?><?= view('subscriptions/period_table',['periods'=>$upcoming,'showStatus'=>true],['saveData'=>false]) ?><?php else: ?><p>No hay más períodos previstos.</p><?php endif ?>
</div></section>
<?php if ($canManage): ?>
<div class="grid gap-6 lg:grid-cols-2 mb-6">
<section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Pausar, reactivar o cancelar</h2><p class="text-sm">Elige el inicio exacto de un período de la proyección. El cambio se aplica desde esa fecha y conserva las deudas anteriores. No se permiten cambios a mitad de período ni sobre períodos ya cobrados.</p>
<form class="grid gap-3" method="post" action="<?= site_url($base.'/estado') ?>" data-confirm="El cambio se aplicará desde la fecha indicada. Las deudas anteriores se conservarán y una cancelación será definitiva."><?= csrf_field() ?><input type="hidden" name="key" value="<?= esc($key.'-state','attr') ?>">
<label class="form-field" for="event-type"><span>Acción</span><select class="select w-full" id="event-type" name="type" required><option value="PAUSA">Pausar servicio</option><option value="REACTIVACION">Reactivar servicio</option><option value="CANCELACION">Cancelar definitivamente</option></select></label>
<label class="form-field" for="event-date"><span>Fecha efectiva (inicio de período)</span><input class="input w-full" id="event-date" value="<?= esc($previous('estado','date'),'attr') ?>" type="date" name="date" required></label>
<label class="form-field" for="event-reason"><span>Motivo</span><input class="input w-full" id="event-reason" value="<?= esc($previous('estado','reason'),'attr') ?>" name="reason" minlength="10" maxlength="500" required></label>
<button class="btn self-start">Registrar cambio de estado</button>
</form></div></section>
<section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Programar nuevo precio</h2><p class="text-sm">El nuevo precio comienza en un período futuro sin cargos. Se conserva el calendario original. Las aceptaciones del precio anterior no autorizan el nuevo importe.</p>
<form class="grid gap-3" method="post" action="<?= site_url($base.'/precio') ?>"><?= csrf_field() ?><input type="hidden" name="key" value="<?= esc($key.'-price','attr') ?>">
<label class="form-field" for="price-date"><span>Primer período con el nuevo precio</span><input class="input w-full" id="price-date" value="<?= esc($previous('precio','date'),'attr') ?>" type="date" name="date" min="<?= esc(date('Y-m-d',strtotime($today.' +1 day')),'attr') ?>" required></label>
<label class="form-field" for="price-amount"><span>Nuevo precio por período (USD)</span><input class="input w-full" id="price-amount" value="<?= esc($previous('precio','amount'),'attr') ?>" type="number" name="amount" min="0.01" step="0.01" required></label>
<label class="form-field" for="price-reason"><span>Motivo del cambio</span><input class="input w-full" id="price-reason" value="<?= esc($previous('precio','reason'),'attr') ?>" name="reason" minlength="10" maxlength="500" required></label>
<button class="btn self-start">Programar precio</button>
</form></div></section>
</div>
<?php if ($latest['requiere_aceptacion']): ?>
<section class="card card-border bg-base-100 mb-6"><div class="card-body"><h2 class="card-title">Aceptar un período</h2><p>Registra la confirmación del cliente y su evidencia. Solo admite el inicio de un período vigente o futuro; una aceptación tardía requiere revisión administrativa.</p>
<form class="grid gap-3 md:grid-cols-2" method="post" action="<?= site_url($base.'/aceptar') ?>"><?= csrf_field() ?><input type="hidden" name="key" value="<?= esc($key.'-accept','attr') ?>">
<label class="form-field" for="accept-date"><span>Inicio del período aceptado</span><select class="select w-full" id="accept-date" name="period" required><option value="">Seleccionar período y precio</option><?php foreach ($upcoming as $p): ?><?php if ($p['status']==='PENDIENTE_ACEPTACION' && $p['start'] >= $today): ?><option value="<?= esc($p['start'].'|'.$p['version'],'attr') ?>"><?= esc($p['start'].' al '.$p['last'].' · $'.$p['amount']) ?></option><?php endif ?><?php endforeach ?></select></label>
<label class="form-field" for="accept-evidence"><span>Evidencia (persona, fecha y referencia del acuerdo)</span><input class="input w-full" id="accept-evidence" value="<?= esc($previous('aceptar','evidence'),'attr') ?>" name="evidence" minlength="10" maxlength="1000" required></label><button class="btn justify-self-start">Registrar aceptación</button></form>
</div></section>
<?php endif ?>
<?php endif ?>
<section class="card card-border bg-base-100 mb-6"><div class="card-body"><h2 class="card-title">Historial de condiciones</h2><div class="overflow-x-auto"><table class="table" data-datatable="off"><caption class="sr-only">Versiones de precio y condiciones de cobro</caption><thead><tr><th scope="col">Desde</th><th scope="col">Precio</th><th scope="col">Cobro</th><th scope="col">Plazo</th><th scope="col">Renovación</th></tr></thead><tbody><?php foreach ($s['conditions'] as $c): ?><tr><td><?= esc($c['vigente_desde']) ?></td><td class="font-data">$<?= esc($c['importe_periodo']) ?></td><td><?= $c['momento_cobro']==='ANTICIPADO' ? 'Al inicio' : 'Al finalizar' ?></td><td><?= (int)$c['plazo_dias'] ?> días</td><td><?= $c['requiere_aceptacion'] ? 'Con aceptación' : 'Automática' ?></td></tr><?php endforeach ?></tbody></table></div>
<?php foreach ($s['events'] as $e): ?><p class="border-t border-base-300 pt-3 mt-3"><strong><?= esc($e['fecha_efectiva'].' · '.$e['tipo']) ?></strong><br><?= esc($e['motivo']) ?></p><?php endforeach ?>
<?php foreach ($s['acceptances'] as $a): ?><p class="border-t border-base-300 pt-3 mt-3"><strong>Aceptación del período <?= esc($a['periodo_desde']) ?> · versión <?= (int)$a['condicion_version'] ?></strong><br><?= esc($a['evidencia']) ?><br><span class="text-sm">Registrada por <?= esc($a['username']) ?> · <?= esc($a['registrada_en']) ?> UTC</span></p><?php endforeach ?>
</div></section>
<?= $this->endSection() ?>
