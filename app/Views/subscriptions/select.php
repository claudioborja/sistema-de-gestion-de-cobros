<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title'=>$title,'description'=>$description], ['saveData'=>false]) ?>
<section class="card card-border max-w-3xl bg-base-100"><div class="card-body">
<form method="get" action="<?= site_url('suscripciones/nueva') ?>" class="grid gap-4">
<label class="form-field" for="subscription-client"><span>Cliente</span><select id="subscription-client" class="select w-full" name="cliente_id" required data-client-select data-active-only="true" data-source="<?= site_url('clientes/datos') ?>"><option value="">Buscar por nombre o identificación</option></select></label>
<div class="flex flex-wrap gap-3"><button class="btn btn-primary">Continuar con el cliente</button><a class="btn" href="<?= site_url('clientes') ?>">Abrir directorio de clientes</a></div>
<noscript>Abre el expediente desde el directorio y selecciona Suscripciones.</noscript>
</form></div></section>
<?= $this->endSection() ?>
