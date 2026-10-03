<?php if (!empty($breadcrumbs)): ?>
<nav class="breadcrumbs -mt-2 mb-1 text-sm text-base-content/65" aria-label="Migas de pan">
    <ul>
    <?php foreach ($breadcrumbs as $crumb): ?>
        <li>
        <?php if (!empty($crumb['url'])): ?>
            <a href="<?= esc($crumb['url'], 'attr') ?>"><?= esc($crumb['label']) ?></a>
        <?php else: ?>
            <span aria-current="page"><?= esc($crumb['label']) ?></span>
        <?php endif ?>
        </li>
    <?php endforeach ?>
    </ul>
</nav>
<?php endif ?>
