<?php
$workflowId = $id ?? 'workflow-steps';
$currentStep = max(1, (int) ($current ?? 1));
$steps = $steps ?? [];
?>
<section class="card card-border mb-5 bg-base-100" aria-labelledby="<?= esc($workflowId, 'attr') ?>-title">
    <div class="card-body gap-3 py-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="<?= esc($workflowId, 'attr') ?>-title" class="text-base font-semibold"><?= esc($title ?? 'Flujo principal') ?></h2>
                <?php if (!empty($description)): ?><p class="mt-1 text-sm text-base-content/70"><?= esc($description) ?></p><?php endif ?>
            </div>
            <span class="badge badge-ghost whitespace-nowrap">Paso <?= $currentStep ?> de <?= count($steps) ?></span>
        </div>
        <ol class="steps steps-vertical w-full sm:steps-horizontal" aria-label="<?= esc($title ?? 'Flujo principal', 'attr') ?>">
            <?php foreach ($steps as $index => $step): ?>
                <?php $number = $index + 1; ?>
                <li class="step <?= $number <= $currentStep ? 'step-primary' : '' ?>" data-content="<?= $number ?>" <?= $number === $currentStep ? 'aria-current="step"' : '' ?>><?= esc($step) ?></li>
            <?php endforeach ?>
        </ol>
        <?php if (!empty($next)): ?><p class="text-sm text-base-content/70"><strong class="text-base-content">Siguiente:</strong> <?= esc($next) ?></p><?php endif ?>
    </div>
</section>
