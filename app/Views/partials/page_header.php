<?= view('partials/breadcrumbs', ['breadcrumbs' => $breadcrumbs ?? []], ['saveData' => false]) ?>
<header class="page-header mb-5">
    <div class="min-w-0">
        <h1 class="text-pretty text-2xl font-semibold leading-tight sm:text-[1.75rem]"><?= esc($title) ?></h1>
        <?php if (($showDescription ?? false) && !empty($description)): ?>
            <p class="mt-1 max-w-3xl text-sm text-base-content/70"><?= esc($description) ?></p>
        <?php endif ?>
    </div>
    <?php if (!empty($primaryAction)): ?>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="<?= esc($primaryAction['url'], 'attr') ?>"<?= !empty($primaryAction['dialog']) ? ' data-dialog-open="' . esc($primaryAction['dialog'], 'attr') . '"' : '' ?>>
            <?php if (!empty($primaryAction['icon'])): ?><i data-lucide="<?= esc($primaryAction['icon'], 'attr') ?>" class="icon" aria-hidden="true"></i><?php endif ?>
            <?= esc($primaryAction['label']) ?>
        </a>
    </div>
    <?php endif ?>
</header>
