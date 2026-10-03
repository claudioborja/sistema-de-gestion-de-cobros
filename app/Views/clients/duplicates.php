<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', [
    'context' => $client['nombre'],
    'title' => $title,
    'description' => $description,
    'primaryAction' => ['label' => 'Volver al expediente', 'url' => site_url('clientes/' . $client['id'] . '/expediente'), 'icon' => 'arrow-left'],
], ['saveData' => false]) ?>

<div class="alert mb-6" role="status"><i data-lucide="info" class="icon" aria-hidden="true"></i><span>Las coincidencias son advertencias. Confirma identidad y contacto antes de decidir.</span></div>
<section class="card card-border bg-base-100" aria-labelledby="matches-title">
    <div class="card-body p-0">
        <header class="section-heading">
            <div>
                <h2 id="matches-title" class="card-title">Coincidencias encontradas</h2>
                <p class="mt-1 text-sm text-base-content/70"><span class="font-data"><?= count($matches) ?></span> registros requieren comparación.</p>
            </div>
            <span class="text-sm font-medium text-base-content/70">Referencia: cliente #<?= $client['id'] ?></span>
        </header>

    <?php if (!$matches): ?>
        <?= view('partials/empty_state', ['icon' => 'badge-check', 'title' => 'No encontramos coincidencias', 'description' => 'El nombre y la identificación no coinciden con otros clientes registrados.'], ['saveData' => false]) ?>
    <?php else: ?>
        <div class="hidden overflow-x-auto md:block">
            <table class="table">
                <caption class="sr-only">Posibles clientes duplicados</caption>
                <thead>
                    <tr>
                        <th scope="col">Cliente candidato</th>
                        <th scope="col">Identificación</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Coincidencia</th>
                        <th scope="col"><span class="sr-only">Acción</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): ?>
                        <tr>
                            <td>
                                <div class="max-w-md break-words font-semibold">#<?= $match['id'] ?> · <?= esc($match['nombre']) ?></div>
                            </td>
                            <td class="font-data"><?= esc($match['identificacion'] ?: 'Sin identificación') ?></td>
                            <td><span class="badge <?= $match['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $match['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                            <td><span class="badge badge-warning badge-soft"><?= esc($match['reason']) ?></span></td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a class="btn" href="<?= site_url('clientes/' . $match['id'] . '/expediente') ?>">Comparar<span class="sr-only"> con <?= esc($match['nombre']) ?></span></a>
                                    <?php if ($canEdit): ?>
                                        <form method="post" action="<?= site_url('clientes/' . $client['id'] . '/descartar-duplicado') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="candidate_id" value="<?= esc((string) $match['id'], 'attr') ?>">
                                            <button class="btn btn-soft">Descartar</button>
                                        </form>
                                    <?php endif ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Posibles clientes duplicados">
            <?php foreach ($matches as $match): ?>
                <li class="list-row items-start gap-3 border-b border-base-300 last:border-b-0">
                    <span class="flex size-10 items-center justify-center rounded-field bg-base-200 font-data text-sm" aria-hidden="true">#<?= $match['id'] ?></span>
                    <div class="list-col-grow min-w-0">
                        <p class="break-words font-semibold"><?= esc($match['nombre']) ?></p>
                        <p class="font-data mt-1 text-sm text-base-content/70"><?= esc($match['identificacion'] ?: 'Sin identificación') ?></p>
                        <div class="mt-2">
                            <span class="badge <?= $match['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $match['activo'] ? 'Activo' : 'Inactivo' ?></span>
                            <span class="badge badge-warning badge-soft ml-2"><?= esc($match['reason']) ?></span>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <a class="btn" href="<?= site_url('clientes/' . $match['id'] . '/expediente') ?>">Comparar<span class="sr-only"> con <?= esc($match['nombre']) ?></span></a>
                        <?php if ($canEdit): ?>
                            <form method="post" action="<?= site_url('clientes/' . $client['id'] . '/descartar-duplicado') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="candidate_id" value="<?= esc((string) $match['id'], 'attr') ?>">
                                <button class="btn btn-soft">Descartar</button>
                            </form>
                        <?php endif ?>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    </div>
</section>

<?= $this->endSection() ?>
