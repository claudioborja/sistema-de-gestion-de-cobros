<?php $path = trim(uri_string(), '/'); ?>
<aside class="app-sidebar h-dvh overflow-hidden bg-neutral text-neutral-content" x-data="navigationState">
    <div class="sidebar-brand shrink-0">
        <a href="<?= site_url() ?>" class="brand-link">
            <span class="brand-mark"><i data-lucide="wallet" class="icon" aria-hidden="true"></i></span>
            <span class="sidebar-copy"><strong>Cobros</strong><small>Gestión</small></span>
        </a>
        <button type="button" class="btn btn-ghost btn-square sidebar-compact-toggle hidden text-neutral-content lg:inline-flex" aria-label="Compactar navegación" aria-pressed="false" x-on:click="toggle">
            <i data-lucide="panel-left-close" class="icon" aria-hidden="true"></i>
        </button>
    </div>
    <nav aria-label="Navegación principal" class="min-h-0 grow overflow-y-auto overscroll-contain">
        <ul class="menu sidebar-menu w-full gap-1 p-3">
        <?php foreach ($navigation as $group): ?>
            <?php
            $groupActive = false;
            foreach ($group['items'] as $groupItem) {
                if ($path === $groupItem['url'] || ($groupItem['url'] !== '' && str_starts_with($path, $groupItem['url'] . '/'))) {
                    $groupActive = true;
                    break;
                }
            }
            ?>
            <li>
                <details <?= $groupActive ? 'open' : '' ?>>
                    <summary class="sidebar-group-summary" title="<?= esc($group['label'], 'attr') ?>">
                        <i data-lucide="<?= esc($group['icon'], 'attr') ?>" class="icon" aria-hidden="true"></i>
                        <span class="sidebar-copy min-w-0 truncate"><?= esc($group['label']) ?></span>
                    </summary>
                    <ul>
                    <?php foreach ($group['items'] as $item): ?>
                        <?php $active = $path === $item['url'] || ($item['url'] !== '' && str_starts_with($path, $item['url'] . '/')); ?>
                        <li>
                            <a href="<?= site_url($item['url']) ?>" class="sidebar-link <?= $active ? 'menu-active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?> title="<?= esc($item['label'], 'attr') ?>">
                                <i data-lucide="<?= esc($item['icon'], 'attr') ?>" class="icon" aria-hidden="true"></i>
                                <span class="sidebar-copy min-w-0 truncate"><?= esc($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach ?>
                    </ul>
                </details>
            </li>
        <?php endforeach ?>
        </ul>
    </nav>
    <div class="sidebar-footer flex min-h-[var(--app-footer-height)] shrink-0 items-center p-3 text-xs leading-5 text-neutral-content/65">
        <span class="font-data">USD · Ecuador</span>
    </div>
</aside>
