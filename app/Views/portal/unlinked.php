<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', ['title' => $title, 'description' => $description], ['saveData' => false]) ?>

<section class="mx-auto max-w-2xl">
    <div class="card card-border bg-base-100"><div class="card-body items-start"><span class="flex size-12 items-center justify-center rounded-field bg-base-200 text-primary"><i data-lucide="mail-key" class="icon" aria-hidden="true"></i></span><h2 class="card-title mt-3">Solicita tu enlace de activación</h2><p class="text-base-content/70">El negocio debe seleccionar tu usuario desde el expediente del cliente y entregarte una invitación personal. No escribas una cédula o RUC para intentar vincularte.</p><div class="alert alert-info alert-soft mt-4" role="status"><i data-lucide="shield-check" class="icon" aria-hidden="true"></i><span>Esta validación evita que una cuenta consulte comprobantes de otro cliente.</span></div></div></div>
</section>

<?= $this->endSection() ?>
