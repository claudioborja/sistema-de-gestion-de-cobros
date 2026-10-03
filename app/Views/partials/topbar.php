<header class="navbar app-topbar border-b border-base-300 bg-base-100 px-4 md:px-6 xl:px-8">
    <div class="navbar-start w-auto min-w-0 flex-1 gap-3">
        <label for="app-drawer" class="btn btn-ghost btn-square drawer-button lg:hidden" aria-label="Abrir navegación">
            <i data-lucide="menu" class="icon" aria-hidden="true"></i>
        </label>
        <div class="min-w-0 text-sm text-base-content/70">
            <span class="font-data hidden sm:inline"><?= esc($currentDate) ?></span>
            <span class="mx-2 hidden text-base-content/30 sm:inline" aria-hidden="true">/</span>
            <span class="hidden truncate sm:inline">Gestión de cobranzas</span>
        </div>
    </div>
    <div class="navbar-end w-auto flex-none gap-2 sm:gap-3">
        <a class="btn btn-ghost max-w-40 sm:max-w-56" href="<?= site_url('mi-cuenta') ?>">
            <i data-lucide="circle-user-round" class="icon" aria-hidden="true"></i>
            <span class="truncate"><?= esc($username) ?></span>
        </a>
        <form method="post" action="<?= site_url('logout') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit">
                <i data-lucide="log-out" class="icon" aria-hidden="true"></i>
                <span class="hidden sm:inline">Salir</span>
            </button>
        </form>
    </div>
</header>
