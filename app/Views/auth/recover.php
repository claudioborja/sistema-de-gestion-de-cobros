<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<main class="auth-shell">
    <section class="auth-intro bg-neutral text-neutral-content">
        <div class="flex items-center gap-3 text-2xl font-semibold"><span class="brand-mark"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span>Cobros</div>
        <div class="ledger-copy"><p class="eyebrow mb-4 text-neutral-content/65">Recupera el acceso</p><h1 class="text-pretty text-4xl font-semibold leading-tight">¿Olvidaste tu contraseña?</h1><p class="mt-5 max-w-sm leading-relaxed text-neutral-content/70">Ingresa tu correo y te enviaremos una vía temporal para restablecerla.</p></div>
    </section>
    <section class="auth-form bg-base-100" aria-labelledby="recover-title">
        <div><h2 id="recover-title" class="text-2xl font-semibold">Recuperar acceso</h2><p class="mt-2 text-sm text-base-content/70">Te enviaremos instrucciones al correo registrado.</p></div>
        <?php if (session('message')): ?><div class="alert" role="status"><?= esc(session('message')) ?></div><?php endif ?>
        <form method="post" action="<?= site_url('recuperar-acceso') ?>" class="grid gap-5">
            <?= csrf_field() ?>
            <label class="form-field" for="email"><span>Correo electrónico</span><input id="email" type="email" name="email" autocomplete="email" required class="input w-full"></label>
            <button class="btn btn-primary mt-2 w-full" type="submit">Enviar enlace<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></button>
        </form>
        <p class="text-sm text-base-content/70"><a href="<?= site_url('login') ?>" class="link link-hover">Volver al inicio</a></p>
    </section>
</main>

<?= $this->endSection() ?>
