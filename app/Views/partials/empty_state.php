<?php $emptyStateAction = is_array($action ?? null) && isset($action['url'], $action['label']) ? $action : null; ?>
<div class="empty-state">
    <i data-lucide="<?= esc($icon ?? 'inbox', 'attr') ?>" class="empty-state-icon" aria-hidden="true"></i>
    <h2 class="text-xl font-semibold"><?= esc($title) ?></h2>
    <p class="mt-2 max-w-lg text-base-content/70"><?= esc($description) ?></p>
    <?php if ($emptyStateAction !== null): ?>
    <a class="btn mt-6" href="<?= esc($emptyStateAction['url'], 'attr') ?>">
        <?php if (!empty($emptyStateAction['icon'])): ?><i data-lucide="<?= esc($emptyStateAction['icon'], 'attr') ?>" class="icon" aria-hidden="true"></i><?php endif ?>
        <?= esc($emptyStateAction['label']) ?>
        <i data-lucide="arrow-right" class="icon" aria-hidden="true"></i>
    </a>
    <?php endif ?>
</div>
