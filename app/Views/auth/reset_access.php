<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<?php $errors = session('errors') ?? []; ?>
<main class="auth-shell">
    <section class="auth-intro bg-neutral text-neutral-content">
        <div class="flex items-center gap-3 text-2xl font-semibold"><span class="brand-mark"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span>Cobros</div>
        <div class="ledger-copy"><p class="eyebrow mb-4 text-neutral-content/65">Acceso seguro</p><h1 class="text-pretty text-4xl font-semibold leading-tight">Elige una nueva contraseña.</h1><p class="mt-5 max-w-sm leading-relaxed text-neutral-content/70">Define una clave larga y guárdala en un lugar seguro.</p></div>
        <p class="text-xs text-neutral-content/60">El enlace tiene validez de 1 hora.</p>
    </section>
    <section class="auth-form bg-base-100" aria-labelledby="reset-title">
        <div><h2 id="reset-title" class="text-2xl font-semibold">Restablecer contraseña</h2><p class="mt-2 text-sm text-base-content/70">Usa la misma cuenta para terminar el acceso.</p></div>
        <?php if (session('error')): ?>
            <div class="alert alert-error" role="alert"><?= esc(session('error')) ?></div>
        <?php endif ?>
        <?php if (session('message')): ?>
            <div class="alert" role="status"><?= esc(session('message')) ?></div>
        <?php endif ?>

        <form method="post" action="<?= site_url('restablecer-acceso/' . urlencode($token)) ?>" class="grid gap-5">
            <?= csrf_field() ?>
            <label class="form-field" for="password">
                <span>Nueva contraseña</span>
                <input id="password" type="password" name="password" autocomplete="new-password" required class="input w-full">
            </label>
            <?php if (isset($errors['password'])): ?>
                <p class="text-sm text-error" role="alert"><?= esc($errors['password']) ?></p>
            <?php endif ?>

            <label class="form-field" for="password_confirm">
                <span>Repite la contraseña</span>
                <input id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" required class="input w-full">
            </label>
            <?php if (isset($errors['password_confirm'])): ?>
                <p class="text-sm text-error" role="alert"><?= esc($errors['password_confirm']) ?></p>
            <?php endif ?>

            <button type="submit" class="btn btn-primary mt-2 w-full">Guardar contraseña</button>
        </form>
        <p class="text-sm text-base-content/70"><a href="<?= site_url('login') ?>" class="link link-hover">Ir a iniciar sesión</a></p>
    </section>
</main>
<?= $this->endSection() ?>
