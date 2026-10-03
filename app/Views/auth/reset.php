<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<main class="auth-shell">
    <section class="auth-intro bg-neutral text-neutral-content">
        <div class="flex items-center gap-3 text-2xl font-semibold"><span class="brand-mark"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span>Cobros</div>
        <div class="ledger-copy"><p class="eyebrow mb-4 text-neutral-content/65">Restablecer credenciales</p><h1 class="text-pretty text-4xl font-semibold leading-tight">Define una nueva contraseña</h1><p class="mt-5 max-w-sm leading-relaxed text-neutral-content/70">Token: <span class="font-data"><?= esc($token) ?></span></p></div>
    </section>
    <section class="auth-form bg-base-100" aria-labelledby="reset-title">
        <div><h2 id="reset-title" class="text-2xl font-semibold">Actualizar contraseña</h2><p class="mt-2 text-sm text-base-content/70">Usa una clave fuerte y conserva la de respaldo en un lugar seguro.</p></div>
        <?php if (session('message')): ?><div class="alert" role="status"><?= esc(session('message')) ?></div><?php endif ?>
        <form method="post" action="<?= site_url('restablecer-acceso/' . $token) ?>" class="grid gap-5">
            <?= csrf_field() ?>
            <label class="form-field" for="password"><span>Nueva contraseña</span><input id="password" type="password" name="password" autocomplete="new-password" minlength="12" required class="input w-full"></label>
            <label class="form-field" for="password_confirm"><span>Confirmar contraseña</span><input id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="12" required class="input w-full"></label>
            <button class="btn btn-primary mt-2 w-full" type="submit">Restablecer<i data-lucide="key-round" class="icon" aria-hidden="true"></i></button>
        </form>
    </section>
</main>

<?= $this->endSection() ?>
