<!doctype html>
<html lang="es" data-theme="cobros">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8fe">
    <meta name="robots" content="noindex,nofollow">
    <title><?= esc($title ?? 'Acceso') ?> · Cobros</title>
    <?= asset_tags() ?>
</head>
<body class="flex min-h-dvh flex-col bg-base-200 text-base-content">
<div class="flex grow items-center">
    <?= $this->renderSection('content') ?>
</div>
<?= view('partials/footer', [], ['saveData' => false]) ?>
</body>
</html>
