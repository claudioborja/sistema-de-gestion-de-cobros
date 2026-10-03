<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>

<?= view('partials/page_header', ['title' => $title, 'description' => $description], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div class="alert alert-error mb-6" role="alert"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><div><h2 class="font-semibold">No se pudo activar el acceso</h2><ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div></div>
<?php endif ?>

<section class="grid items-start gap-6 lg:grid-cols-12">
    <form method="post" action="<?= site_url('portal/invitacion/' . $token) ?>" class="card card-border bg-base-100 lg:col-span-8">
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
            <p class="eyebrow">Invitación personal</p>
            <h2 class="card-title mt-1">Vincular tu cuenta con <?= esc($invitation['cliente']) ?></h2>
            <p class="mt-3 max-w-2xl text-base-content/70">La cuenta <strong><?= esc($invitation['username']) ?></strong> podrá registrar comprobantes de este cliente y consultar únicamente su revisión.</p>
            <div class="alert alert-info alert-soft mt-6" role="status"><i data-lucide="shield-check" class="icon" aria-hidden="true"></i><span>Este acceso no muestra cartera, cuotas, pagos confirmados, recibos ni estados de cuenta.</span></div>
            <div class="form-actions"><a class="btn" href="<?= site_url('portal') ?>">No activar</a><button class="btn btn-primary" type="submit"><i data-lucide="link" class="icon" aria-hidden="true"></i>Activar acceso</button></div>
        </div>
    </form>
    <aside class="card card-border bg-base-100 lg:col-span-4"><div class="card-body"><h2 class="card-title">Vigencia</h2><p class="text-sm text-base-content/70">La invitación vence el <span class="font-data"><?= esc($invitation['expira_en_local']) ?></span> y solo puede utilizarse una vez.</p><p class="mt-3 text-sm text-base-content/70">Si no reconoces al cliente, no actives el acceso y comunícate con el negocio.</p></div></aside>
</section>

<?= $this->endSection() ?>
