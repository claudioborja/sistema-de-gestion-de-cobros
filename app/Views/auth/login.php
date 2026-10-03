<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<main class="auth-shell">
    <section class="auth-intro bg-neutral text-neutral-content">
        <div class="flex items-center gap-3 text-2xl font-semibold"><span class="brand-mark"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span>Cobros</div>
        <div class="ledger-copy"><p class="eyebrow mb-4 text-neutral-content/65">Tu negocio, en orden</p><h1 class="text-pretty text-4xl font-semibold leading-tight">Cada cliente.<br>Cada movimiento.<br>Todo claro.</h1><p class="mt-5 max-w-sm leading-relaxed text-neutral-content/70">Un espacio de trabajo para organizar tus clientes y dar seguimiento a tu negocio.</p></div>
        <p class="text-xs text-neutral-content/60">Acceso privado · Usuarios autorizados</p>
    </section>
    <section class="auth-form bg-base-100" aria-labelledby="login-title">
        <div><h2 id="login-title" class="text-2xl font-semibold">Bienvenido de nuevo</h2><p class="mt-2 text-sm text-base-content/70">Ingresa con tu cuenta de trabajo.</p></div>
        <?php if (session('error')): ?><div class="alert alert-error" role="alert">No se pudo iniciar sesión. Revisa tus credenciales.</div><?php endif ?>
        <?php if (session('errors')): ?><div class="alert alert-error" role="alert">Completa un correo y una contraseña válidos.</div><?php endif ?>
        <form method="post" action="<?= site_url('login') ?>" class="grid gap-5">
            <?= csrf_field() ?>
            <label class="form-field" for="email"><span>Correo electrónico</span><input id="email" type="email" name="email" autocomplete="username" spellcheck="false" required class="input w-full" value="<?= esc(old('email'), 'attr') ?>"></label>
            <label class="form-field" for="password"><span>Contraseña</span><input id="password" type="password" name="password" autocomplete="current-password" required class="input w-full"></label>
            <button type="submit" class="btn btn-primary mt-2 w-full">Iniciar sesión<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></button>
        </form>
        <?php if (session('message')): ?><div class="alert" role="status"><?= esc(session('message')) ?></div><?php endif ?>
        <p class="text-sm text-base-content/70"><a href="<?= site_url('recuperar-acceso') ?>" class="link link-hover">¿Olvidaste tu contraseña?</a></p>
        <p class="text-sm text-base-content/70">Si necesitas acceso, contacta al administrador.</p>
    </section>
</main>
<?= $this->endSection() ?>
