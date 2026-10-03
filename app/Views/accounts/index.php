<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', ['context' => 'Acceso y seguridad', 'title' => $title, 'description' => $description, 'primaryAction' => $canCreate ? ['label' => 'Nuevo usuario', 'url' => site_url('usuarios/nuevo'), 'icon' => 'user-plus'] : null], ['saveData' => false]) ?>

<section class="card card-border bg-base-100" aria-labelledby="users-title">
    <div class="card-body p-0"><header class="section-heading"><div><h2 id="users-title" class="card-title">Directorio de acceso</h2><p class="mt-1 text-sm text-base-content/70"><span class="font-data"><?= count($users) ?></span> cuentas registradas.</p></div></header>
    <?php if (!$users): ?>
        <?= view('partials/empty_state', ['icon' => 'users-round', 'title' => 'No hay usuarios para mostrar', 'description' => 'Las cuentas habilitadas aparecerán en este directorio.'], ['saveData' => false]) ?>
    <?php else: ?>
        <div class="hidden overflow-x-auto md:block"><table class="table" data-datatable="local"><caption class="sr-only">Usuarios y funciones asignadas</caption><thead><tr><th scope="col">Usuario</th><th scope="col">Correo</th><th scope="col">Función</th><th scope="col">Último acceso</th><th scope="col">Estado</th><th scope="col" class="dt-actions"><span class="sr-only">Acciones</span></th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><strong class="break-words"><?= esc($user['username']) ?></strong><p class="font-data text-xs text-base-content/70">Usuario #<?= $user['id'] ?></p></td><td class="break-words"><?= esc($user['email'] ?: 'No informado') ?></td><td><?= esc(implode(', ', $user['groups']) ?: 'Sin función') ?></td><td class="font-data whitespace-nowrap"><?= esc($user['lastActive']) ?></td><td><span class="badge <?= $user['active'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $user['active'] ? 'Activo' : 'Restringido' ?></span></td><td class="text-right"><a class="btn" href="<?= site_url('usuarios/' . $user['id']) ?>">Editar<span class="sr-only"> <?= esc($user['username']) ?></span></a></td></tr><?php endforeach ?></tbody></table></div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Usuarios y funciones asignadas"><?php foreach ($users as $user): ?><li class="record-card"><div class="min-w-0"><p class="break-words font-semibold"><?= esc($user['username']) ?></p><p class="mt-1 break-words text-sm text-base-content/70"><?= esc($user['email'] ?: 'Sin correo') ?></p><p class="mt-2 text-sm"><?= esc(implode(', ', $user['groups']) ?: 'Sin función') ?></p><p class="font-data mt-2 text-sm text-base-content/70">Último acceso: <?= esc($user['lastActive']) ?></p></div><div class="flex flex-col items-end gap-2"><span class="badge <?= $user['active'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $user['active'] ? 'Activo' : 'Restringido' ?></span><a class="btn" href="<?= site_url('usuarios/' . $user['id']) ?>">Editar<span class="sr-only"> <?= esc($user['username']) ?></span></a></div></li><?php endforeach ?></ul>
    <?php endif ?>
    </div>
</section>

<?= $this->endSection() ?>
