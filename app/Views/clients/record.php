<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', [
    'context' => 'Expediente del cliente',
    'title' => $client['nombre'],
    'description' => $description,
    'primaryAction' => $canEdit ? ['label' => 'Editar cliente', 'url' => site_url('clientes/' . $client['id']), 'icon' => 'pencil'] : null,
], ['saveData' => false]) ?>

<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>
<?= view('partials/workflow_steps', [
    'id' => 'client-record-workflow',
    'title' => 'Gestión del cliente',
    'description' => 'El cliente ya está identificado. Revisa sus datos antes de abrir una operación.',
    'steps' => ['Cliente seleccionado', 'Revisar expediente', 'Documentar o cobrar', 'Consultar resultado'],
    'current' => 2,
    'next' => 'Elige Documentos, Cartera o Cobros sin perder el contexto de este cliente.',
], ['saveData' => false]) ?>
<div class="mb-6 flex flex-wrap gap-3">
    <?php if ($client['activo'] && \App\Services\Access::can((int) auth()->id(), 'pagos.crear')): ?><a class="btn btn-primary" href="<?= site_url('clientes/' . (int) $client['id'] . '/pagos/nuevo') ?>"><i data-lucide="banknote" class="icon" aria-hidden="true"></i>Registrar cobro</a><?php endif ?>
    <?php if ($client['activo'] && \App\Services\Access::can((int) auth()->id(), 'cartera.ver')): ?><a class="btn" href="<?= site_url('clientes/' . (int) $client['id'] . '/documentos/nuevo') ?>"><i data-lucide="file-text" class="icon" aria-hidden="true"></i>Nuevo documento</a><?php endif ?>
</div>
<section class="mt-8 grid items-start gap-5 lg:grid-cols-12">
    <div class="card card-border bg-base-100 lg:col-span-8">
        <div class="card-body">
            <h2 class="card-title">Resumen del expediente</h2>
            <p class="mt-1 text-sm text-base-content/70">Clave de entidad y estado para ubicar rápido al cliente.</p>
            <div class="stats mt-4 w-full stats-vertical md:stats-horizontal">
                <div class="stat ledger-stat">
                    <div class="stat-title">Estado</div>
                    <div class="stat-value"><span class="badge <?= $client['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $client['activo'] ? 'Activo' : 'Inactivo' ?></span></div>
                    <div class="stat-desc mt-2">Versión <span class="font-data"><?= $client['version'] ?></span></div>
                </div>
                <div class="stat">
                    <div class="stat-title">Identificación</div>
                    <div class="stat-value font-data break-words text-xl"><?= esc($client['identificacion'] ?: 'No informada') ?></div>
                    <div class="stat-desc mt-2"><?= esc($client['tipo'] ?? '-') ?> · <?= esc($client['pais'] ?? '-') ?></div>
                </div>
                <div class="stat">
                    <div class="stat-title">Registro</div>
                    <div class="stat-value font-data text-xl">#<?= $client['id'] ?></div>
                    <div class="stat-desc mt-2"><?= esc(substr((string) $client['creado_en'], 0, 10)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <section class="card card-border bg-base-100 lg:col-span-4" aria-labelledby="contact-title">
        <div class="card-body">
            <h2 id="contact-title" class="card-title">Contactos</h2>
            <dl class="mt-3 grid gap-4 text-sm">
                <div>
                    <dt class="text-base-content/70">Correo electrónico</dt>
                    <dd class="mt-1 break-words font-medium"><?= esc($client['email'] ?: 'No informado') ?></dd>
                </div>
                <div>
                    <dt class="text-base-content/70">Teléfono</dt>
                    <dd class="mt-1 break-words font-data"><?= esc($client['telefono'] ?: 'No informado') ?></dd>
                </div>
                <div>
                    <dt class="text-base-content/70">Dirección</dt>
                    <dd class="mt-1 break-words"><?= esc($client['direccion'] ?: 'No informada') ?></dd>
                </div>
            </dl>
            <div class="card-actions mt-4 flex flex-wrap">
                <a class="btn" href="<?= site_url('clientes/' . $client['id'] . '/duplicados') ?>">
                    <i data-lucide="scan-search" class="icon" aria-hidden="true"></i>
                    Ver duplicados
                </a>
                <a class="btn btn-soft" href="<?= site_url('clientes/' . $client['id'] . '/estado-cuenta') ?>">
                    <i data-lucide="file-bar-chart" class="icon" aria-hidden="true"></i>
                    Estado de cuenta
                </a>
                <a class="btn btn-soft" href="<?= site_url('clientes/' . $client['id'] . '/credito') ?>">
                    <i data-lucide="badge-dollar-sign" class="icon" aria-hidden="true"></i>
                    Crédito
                </a>
                <?php if ($canEdit): ?>
                    <a class="btn" href="<?= site_url('clientes/' . $client['id']) ?>">
                        <i data-lucide="pencil" class="icon" aria-hidden="true"></i>
                        Editar registro
                    </a>
                <?php endif ?>
            </div>
        </div>
    </section>
</section>

<?php if ($canInvitePortal): ?>
<section class="card card-border mt-8 bg-base-100" aria-labelledby="portal-access-title">
    <div class="card-body">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h2 id="portal-access-title" class="card-title">Acceso al portal</h2><p class="mt-1 text-sm text-base-content/70">Crea una invitación personal para un usuario cliente todavía no vinculado.</p></div><span class="badge badge-info badge-soft"><i data-lucide="shield-check" class="icon" aria-hidden="true"></i>Acceso limitado</span></div>
        <?php if ($portalInvitationUrl): ?>
        <div class="alert alert-success alert-soft mt-5" role="status"><i data-lucide="link" class="icon" aria-hidden="true"></i><div class="min-w-0"><p class="font-semibold">Enlace creado; se mostrará solo en esta respuesta</p><p class="font-data mt-1 break-all text-sm"><?= esc($portalInvitationUrl) ?></p></div></div>
        <?php endif ?>
        <?php $portalErrors = session('errors') ?? []; if (isset($portalErrors['usuario_id'])): ?><div class="alert alert-error alert-soft mt-5" role="alert"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><span><?= esc($portalErrors['usuario_id']) ?></span></div><?php endif ?>
        <?php if ($portalCandidates): ?>
        <form method="post" action="<?= site_url('clientes/' . (int) $client['id'] . '/invitaciones-portal') ?>" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
            <?= csrf_field() ?>
            <label class="form-field min-w-0 flex-1" for="usuario_id"><span>Usuario cliente *</span><select class="select w-full" id="usuario_id" name="usuario_id" required><option value="">Selecciona un usuario</option><?php foreach ($portalCandidates as $candidate): ?><option value="<?= (int) $candidate['id'] ?>" <?= (string) old('usuario_id', '') === (string) $candidate['id'] ? 'selected' : '' ?>><?= esc($candidate['username'] . ($candidate['email'] !== '' ? ' · ' . $candidate['email'] : '')) ?></option><?php endforeach ?></select><span class="text-sm text-base-content/70">La invitación vencerá en siete días y solo funcionará para este usuario.</span></label>
            <button class="btn btn-primary" type="submit"><i data-lucide="mail-plus" class="icon" aria-hidden="true"></i>Crear invitación</button>
        </form>
        <?php else: ?>
        <div class="alert alert-info alert-soft mt-5" role="status"><i data-lucide="user-round-check" class="icon" aria-hidden="true"></i><span>No hay usuarios cliente activos disponibles. Crea el usuario o revisa sus vinculaciones.</span></div>
        <?php endif ?>
    </div>
</section>
<?php endif ?>

<section class="card card-border mt-8 bg-base-100" aria-labelledby="history-title">
    <div class="card-body p-0">
        <header class="section-heading flex-col items-start sm:flex-row sm:items-center">
            <div>
                <h2 id="history-title" class="card-title">Actividad del expediente</h2>
                <p class="mt-1 text-sm text-base-content/70">Historial reciente y actor que realizó cada acción.</p>
            </div>
            <span class="text-sm font-medium text-base-content/70">Últimos <?= count($events) ?> movimientos</span>
        </header>
        <?php if (!$events): ?>
            <?= view('partials/empty_state', ['icon' => 'history', 'title' => 'Sin actividad registrada', 'description' => 'Los cambios del cliente aparecerán aquí en cuanto se guarde una acción.'], ['saveData' => false]) ?>
        <?php else: ?>
            <div class="hidden overflow-x-auto md:block">
                <table class="table">
                    <caption class="sr-only">Historial del cliente</caption>
                    <thead>
                        <tr><th scope="col">Evento</th><th scope="col">Actor</th><th scope="col">Fecha</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td class="font-medium"><?= esc($event['label']) ?></td>
                                <td><?= esc($event['actor']) ?></td>
                                <td class="font-data text-sm text-base-content/70"><?= esc($event['fecha']) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <ul class="list divide-y divide-base-300 md:hidden" aria-label="Historial del cliente">
                <?php foreach ($events as $event): ?>
                    <li class="list-row items-start gap-3">
                        <i data-lucide="clock-3" class="icon text-primary" aria-hidden="true"></i>
                        <div class="min-w-0">
                            <p class="font-medium"><?= esc($event['label']) ?></p>
                            <p class="mt-1 text-sm text-base-content/70">Actor: <?= esc($event['actor']) ?></p>
                            <time class="mt-2 block font-data text-sm text-base-content/70"><?= esc($event['fecha']) ?></time>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</section>

<?= $this->endSection() ?>
