<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<main class="auth-shell">
    <section class="auth-intro bg-neutral text-neutral-content">
        <div class="flex items-center gap-3 text-2xl font-semibold"><span class="brand-mark"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span>Cobros</div>
        <div class="ledger-copy"><p class="eyebrow mb-4 text-neutral-content/65">Recupera tu acceso</p><h1 class="text-pretty text-4xl font-semibold leading-tight">No pierdas el control de<br>tu cuenta.</h1><p class="mt-5 max-w-sm leading-relaxed text-neutral-content/70">Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.</p></div>
        <p class="text-xs text-neutral-content/60">Acceso privado · Enlace único y con vencimiento</p>
    </section>
    <section class="auth-form bg-base-100" aria-labelledby="recover-title">
        <div><h2 id="recover-title" class="text-2xl font-semibold">Recuperar acceso</h2><p class="mt-2 text-sm text-base-content/70">Te ayudamos a volver a entrar.</p></div>
        <?php if (session('error')): ?>
            <div class="alert alert-error" role="alert"><?= esc(session('error')) ?></div>
        <?php endif ?>
        <?php if (session('message')): ?>
            <div class="alert" role="status"><?= esc(session('message')) ?></div>
        <?php endif ?>

        <form method="post" action="<?= site_url('recuperar-acceso') ?>" class="grid gap-5">
            <?= csrf_field() ?>
            <label class="form-field" for="email">
                <span>Correo electrónico</span>
                <input id="email" type="email" name="email" autocomplete="username" spellcheck="false" required class="input w-full" value="<?= esc(old('email'), 'attr') ?>">
            </label>
            <?php if (isset($errors['email'])): ?>
                <p class="text-sm text-error" role="alert"><?= esc($errors['email']) ?></p>
            <?php endif ?>
            <button type="submit" class="btn btn-primary mt-2 w-full">Enviar enlace</button>
        </form>

        <p class="text-sm text-base-content/70"><a href="<?= site_url('login') ?>" class="link link-hover">Volver al inicio de sesión</a></p>
    </section>
</main>
<?= $this->endSection() ?>
