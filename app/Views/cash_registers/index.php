<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'primaryAction' => ['label' => 'Nueva caja', 'url' => site_url('cajas/nueva'), 'icon' => 'plus'],
], ['saveData' => false]) ?>

<section class="card card-border bg-base-100" aria-label="Cajas físicas registradas">
    <?php if (!$rows): ?>
        <?= view('partials/empty_state', ['icon' => 'wallet', 'title' => 'Aún no hay cajas', 'description' => 'Registra el código y el nombre de cada punto físico de cobro.'], ['saveData' => false]) ?>
    <?php else: ?>
        <div class="hidden overflow-x-auto md:block">
            <table class="table">
                <caption class="sr-only">Cajas físicas y estado del registro</caption>
                <thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Estado</th><th scope="col" class="text-right">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <th scope="row" class="font-data"><?= esc($row['codigo']) ?></th>
                        <td class="break-words"><?= esc($row['nombre']) ?></td>
                        <td><span class="badge <?= $row['activa'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $row['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
                        <td class="text-right"><a class="btn" href="<?= site_url('cajas/' . (int) $row['id'] . '/editar') ?>"><i data-lucide="pencil" class="icon" aria-hidden="true"></i>Editar<span class="sr-only"> <?= esc($row['nombre']) ?></span></a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Cajas físicas">
        <?php foreach ($rows as $row): ?>
            <li class="record-card items-start">
                <div class="min-w-0 grow"><p class="break-words font-semibold"><?= esc($row['nombre']) ?></p><p class="font-data mt-1 break-all text-sm text-base-content/70"><?= esc($row['codigo']) ?></p></div>
                <div class="flex shrink-0 flex-col items-end gap-3"><span class="badge <?= $row['activa'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $row['activa'] ? 'Activa' : 'Inactiva' ?></span><a class="btn" href="<?= site_url('cajas/' . (int) $row['id'] . '/editar') ?>"><i data-lucide="pencil" class="icon" aria-hidden="true"></i>Editar<span class="sr-only"> <?= esc($row['nombre']) ?></span></a></div>
            </li>
        <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
