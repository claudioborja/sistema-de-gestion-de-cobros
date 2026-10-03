<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<main class="access-shell grid min-h-dvh place-items-center p-4">
    <section class="card card-border w-full max-w-lg bg-base-100" aria-labelledby="access-title"><div class="card-body p-8"><span class="brand-mark mb-2"><i data-lucide="shield-alert" class="icon" aria-hidden="true"></i></span><h1 id="access-title" class="text-2xl font-semibold"><?= esc($title ?? 'Acceso restringido') ?></h1><p class="mt-2 text-base-content/70"><?= esc($message ?? 'Tu cuenta no tiene permiso para abrir esta sección.') ?></p><div class="card-actions mt-4"><form method="post" action="<?= site_url('logout') ?>"><?= csrf_field() ?><button type="submit" class="btn">Cerrar sesión</button></form></div></div></section>
</main>
<?= $this->endSection() ?>
