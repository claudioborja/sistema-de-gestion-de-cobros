<!doctype html><html lang="es" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Iniciar sesión · Cobros</title><?= asset_tags() ?></head>
<body class="bg-base-200 min-h-dvh grid place-items-center p-5">
<main class="grid w-full max-w-4xl md:grid-cols-2 overflow-hidden rounded-2xl border border-base-300 bg-base-100">
<section class="bg-neutral text-neutral-content p-8 md:p-12 flex flex-col justify-between gap-14">
<div class="flex items-center gap-3 text-2xl font-bold"><i data-lucide="wallet" aria-hidden="true"></i>Cobros</div>
<div><p class="text-xs tracking-widest uppercase text-white/60 mb-4">Tu negocio, en orden</p><h1 class="text-4xl font-semibold leading-tight">Cada cliente.<br>Cada movimiento.<br>Todo claro.</h1><p class="mt-5 text-white/70 leading-relaxed">Un espacio de trabajo para organizar tus clientes y dar seguimiento a tu negocio.</p></div>
<p class="text-xs text-white/60">Acceso privado · Usuarios autorizados</p>
</section>
<section class="p-8 md:p-12">
<h2 class="text-2xl font-semibold mb-2">Bienvenido de nuevo</h2><p class="text-sm text-base-content/65 mb-8">Ingresa con tu cuenta de trabajo.</p>
<?php if (session('error')): ?><div class="alert alert-error mb-5" role="alert">No se pudo iniciar sesión. Revisa tus credenciales.</div><?php endif ?>
<?php if (session('message')): ?><div class="alert mb-5" role="status">Sesión cerrada.</div><?php endif ?>
<?php if (session('errors')): ?><div class="alert alert-error mb-5" role="alert">Completa un correo y una contraseña válidos.</div><?php endif ?>
<form method="post" action="<?= site_url('login') ?>" class="grid gap-5">
<?= csrf_field() ?>
<label class="field" for="email">Correo electrónico<input id="email" type="email" name="email" autocomplete="username" required class="input" value="<?= old('email') ?>"></label>
<label class="field" for="password">Contraseña<input id="password" type="password" name="password" autocomplete="current-password" required class="input"></label>
<button type="submit" class="btn btn-primary w-full mt-2">Iniciar sesión<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></button>
</form><p class="text-sm text-base-content/60 mt-8">Si necesitas acceso, contacta al administrador.</p>
</section></main></body></html>
