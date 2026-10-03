<!doctype html>
<html lang="es" data-theme="cobros" data-sidebar="expanded">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8fe">
    <meta name="robots" content="noindex,nofollow">
    <title><?= esc($title ?? 'Inicio') ?> · Cobros</title>
    <?= asset_tags() ?>
</head>
<body class="min-h-dvh bg-base-200 text-base-content">
<a href="#contenido" class="skip-link">Saltar al contenido</a>
<div class="drawer lg:drawer-open">
    <input id="app-drawer" type="checkbox" class="drawer-toggle" aria-label="Alternar navegación">
    <div class="app-content-shell drawer-content flex min-h-dvh min-w-0 flex-col">
        <?= view('partials/topbar', ['currentDate' => $currentDate ?? '', 'username' => $username ?? ''], ['saveData' => false]) ?>
        <main id="contenido" class="page-container grow" data-page-pattern="<?= esc($pagePattern ?? 'general', 'attr') ?>" tabindex="-1">
            <?= view('partials/feedback', [], ['saveData' => false]) ?>
            <?= $this->renderSection('content') ?>
        </main>
        <?= view('partials/footer', ['fixed' => true], ['saveData' => false]) ?>
    </div>
    <div class="drawer-side z-40">
        <label for="app-drawer" aria-label="Cerrar navegación" class="drawer-overlay"></label>
        <?= view('partials/sidebar', ['navigation' => $navigation ?? []], ['saveData' => false]) ?>
    </div>
</div>
</body>
</html>
