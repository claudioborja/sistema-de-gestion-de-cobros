<!doctype html>
<html lang="es" data-theme="cobros">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8fe">
    <meta name="robots" content="noindex,nofollow">
    <title><?= esc($title ?? 'Portal') ?> · Cobros</title>
    <?= asset_tags() ?>
</head>
<body class="flex min-h-dvh flex-col bg-base-200 text-base-content">
<a href="#contenido" class="skip-link">Saltar al contenido</a>
<header class="border-b border-base-300 bg-base-100">
    <div class="mx-auto flex min-h-16 max-w-6xl flex-wrap items-center gap-3 px-4 py-2 sm:px-6 lg:px-8">
        <a class="mr-auto flex items-center gap-3 font-semibold" href="<?= site_url('portal') ?>">
            <span class="flex size-10 items-center justify-center rounded-field bg-primary text-primary-content"><i data-lucide="receipt-text" class="icon" aria-hidden="true"></i></span>
            <span>Portal de comprobantes</span>
        </a>
        <?php if (!empty($portalLinked)): ?><nav class="order-3 flex w-full gap-1 sm:order-2 sm:w-auto" aria-label="Portal del cliente">
            <a class="btn btn-ghost flex-1 sm:flex-none" href="<?= site_url('portal/reportes-pago') ?>"><i data-lucide="list" class="icon" aria-hidden="true"></i>Mis comprobantes</a>
            <a class="btn btn-ghost flex-1 sm:flex-none" href="<?= site_url('portal/reportes-pago/nuevo') ?>"><i data-lucide="upload" class="icon" aria-hidden="true"></i>Registrar</a>
        </nav><?php endif ?>
        <div class="order-2 flex items-center gap-1 sm:order-3">
            <span class="hidden max-w-40 truncate text-sm text-base-content/70 md:inline"><?= esc($username ?? '') ?></span>
            <form method="post" action="<?= site_url('logout') ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><i data-lucide="log-out" class="icon" aria-hidden="true"></i><span class="sr-only sm:not-sr-only">Salir</span></button></form>
        </div>
    </div>
</header>
<main id="contenido" class="mx-auto w-full max-w-6xl grow px-4 py-6 sm:px-6 lg:px-8" data-page-pattern="<?= esc($pagePattern ?? 'general', 'attr') ?>" tabindex="-1">
    <?= view('partials/feedback', [], ['saveData' => false]) ?>
    <?= $this->renderSection('content') ?>
</main>
<?= view('partials/footer', [], ['saveData' => false]) ?>
</body>
</html>
