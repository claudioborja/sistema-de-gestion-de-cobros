<?php if ($message = session('message')): ?>
<div class="alert alert-success mb-6" role="status" data-toast-success="<?= esc($message, 'attr') ?>">
    <i data-lucide="circle-check" class="icon" aria-hidden="true"></i>
    <span><?= esc($message) ?></span>
</div>
<?php endif ?>
<?php if ($error = session('error')): ?>
<div class="alert alert-error mb-6" role="alert">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <span><?= esc($error) ?></span>
</div>
<?php endif ?>
