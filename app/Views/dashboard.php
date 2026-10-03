<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', [
    'title' => 'Buen día, ' . $username . '.',
    'primaryAction' => $canCreateClient ? [
        'label' => 'Nuevo cliente',
        'url' => site_url('clientes/nuevo'),
        'icon' => 'plus',
    ] : null,
], ['saveData' => false]) ?>

<div data-dashboard="<?= esc($dashboardMode, 'attr') ?>">
<section class="stats stats-vertical w-full border border-base-300 bg-base-100 lg:stats-horizontal" aria-label="Resumen operativo">
    <div class="stat ledger-stat">
        <div class="stat-title">Clientes registrados</div>
        <div class="stat-value font-data text-3xl" data-metric="clients-total"><?= $clientsTotal ?></div>
        <div class="stat-desc mt-2"><?= $clientsInactive ?> <?= $clientsInactive === 1 ? 'requiere' : 'requieren' ?> revisión de estado</div>
    </div>
    <div class="stat">
        <div class="stat-title">Clientes activos</div>
        <div class="stat-value font-data text-3xl" data-metric="clients-active"><?= $clientsActive ?></div>
        <div class="stat-desc mt-2">Disponibles para operar</div>
    </div>
    <?php if ($canManageCatalog): ?>
    <div class="stat">
        <div class="stat-title">Catálogo disponible</div>
        <div class="stat-value font-data text-3xl" data-metric="catalog-active"><?= $catalogActive ?> de <?= $catalogTotal ?></div>
        <div class="stat-desc mt-2">Ítems activos del total registrado</div>
    </div>
    <?php endif ?>
</section>

<section class="card card-border mt-5 bg-base-100" aria-labelledby="daily-workflow-title">
    <div class="card-body gap-3">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 id="daily-workflow-title" class="card-title">Trabajar desde el cliente</h2><p class="mt-1 text-sm text-base-content/70">Busca el cliente, abre su expediente y registra allí la operación.</p></div>
            <a class="btn btn-primary" href="<?= site_url('clientes') ?>"><i data-lucide="users" class="icon" aria-hidden="true"></i>Abrir clientes</a>
        </div>
        <ol class="steps steps-vertical w-full lg:steps-horizontal" aria-label="Flujo principal de cobranza">
            <li class="step step-primary" data-content="1">Buscar cliente</li>
            <li class="step" data-content="2">Revisar expediente</li>
            <li class="step" data-content="3">Documentar o cobrar</li>
            <li class="step" data-content="4">Consultar resultado</li>
        </ol>
    </div>
</section>

<div class="mt-5 grid gap-5 xl:grid-cols-12">
    <section class="card card-border bg-base-100 xl:col-span-8" aria-labelledby="recent-clients-title">
        <div class="card-body p-0">
            <header class="section-heading">
                <div>
                    <h2 id="recent-clients-title" class="card-title">Clientes recientes</h2>
                    <p class="mt-1 text-sm text-base-content/70">Últimos registros incorporados al directorio.</p>
                </div>
                <a class="btn" href="<?= site_url('clientes') ?>">Ver todos<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></a>
            </header>
            <?php if (!$recentClients): ?>
                <?= view('partials/empty_state', [
                    'icon' => 'users',
                    'title' => 'Empieza por tus clientes',
                    'description' => 'Registra sus datos de contacto para tenerlos a mano.',
                    'action' => $canCreateClient ? ['label' => 'Nuevo cliente', 'url' => site_url('clientes/nuevo')] : null,
                ], ['saveData' => false]) ?>
            <?php else: ?>
                <ul class="list" aria-label="Clientes registrados recientemente">
                <?php foreach ($recentClients as $client): ?>
                    <li class="list-row items-center border-b border-base-300 last:border-b-0">
                        <span class="flex size-10 items-center justify-center rounded-field bg-base-200 font-data text-sm" aria-hidden="true">#<?= $client['id'] ?></span>
                        <div class="list-col-grow min-w-0">
                            <p class="break-words font-semibold"><?= esc($client['nombre']) ?></p>
                            <p class="mt-1 text-sm text-base-content/70">Registro de cliente</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 [grid-column:2] [grid-row:2] sm:[grid-column:auto] sm:[grid-row:1]">
                            <span class="badge <?= $client['activo'] ? 'badge-success badge-soft' : 'badge-ghost' ?>"><?= $client['activo'] ? 'Activo' : 'Inactivo' ?></span>
                            <a class="btn" href="<?= site_url('clientes/' . $client['id'] . '/expediente') ?>">Expediente<span class="sr-only"> de <?= esc($client['nombre']) ?></span></a>
                        </div>
                    </li>
                <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </section>

    <aside class="card card-border bg-base-100 xl:col-span-4" aria-labelledby="readiness-title">
        <div class="card-body gap-5">
            <div>
                <h2 id="readiness-title" class="card-title">Preparación del directorio</h2>
                <p class="mt-2 text-sm text-base-content/70">Comprueba que existan clientes e ítems activos para trabajar.</p>
            </div>
            <div>
                <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                    <span>Datos preparados</span>
                    <strong class="font-data"><?= $readiness ?>%</strong>
                </div>
                <progress class="progress progress-primary" value="<?= $readiness ?>" max="100" aria-label="Datos maestros preparados al <?= $readiness ?> por ciento"></progress>
            </div>
            <ul class="list">
            <?php foreach ($readinessChecks as $check): ?>
                <li class="list-row items-center px-0">
                    <i data-lucide="<?= $check['ready'] ? 'circle-check' : 'circle-alert' ?>" class="icon <?= $check['ready'] ? 'text-success' : 'text-warning' ?>" aria-hidden="true"></i>
                    <span class="font-medium"><?= esc($check['label']) ?></span>
                    <span class="badge <?= $check['ready'] ? 'badge-success badge-soft' : 'badge-warning badge-soft' ?>"><?= $check['ready'] ? 'Listo' : 'Pendiente' ?></span>
                </li>
            <?php endforeach ?>
            </ul>
            <div class="card-actions flex-col items-stretch">
                <?php if ($canManageCatalog): ?><a class="btn" href="<?= site_url('catalogo/nuevo') ?>"><i data-lucide="package" class="icon" aria-hidden="true"></i>Nuevo ítem</a><?php endif ?>
            </div>
        </div>
    </aside>
</div>

<?php if ($canViewAudit): ?>
<section class="card card-border mt-5 bg-base-100" aria-labelledby="activity-title">
    <div class="card-body p-0">
        <header class="section-heading">
            <div>
                <h2 id="activity-title" class="card-title">Actividad reciente</h2>
                <p class="mt-1 text-sm text-base-content/70">Tus últimas acciones registradas por el sistema.</p>
            </div>
            <span class="badge badge-ghost">Solo tu actividad</span>
        </header>
        <?php if (!$recentActivity): ?>
            <?= view('partials/empty_state', [
                'icon' => 'inbox',
                'title' => 'Aún no hay actividad registrada',
                'description' => 'Las altas y modificaciones aparecerán aquí cuando trabajes con el sistema.',
            ], ['saveData' => false]) ?>
        <?php else: ?>
            <ul class="list" aria-label="Actividad administrativa reciente">
            <?php foreach ($recentActivity as $activity): ?>
                <li class="list-row items-center border-b border-base-300 last:border-b-0">
                    <i data-lucide="check" class="icon text-success" aria-hidden="true"></i>
                    <div class="min-w-0">
                        <p class="font-semibold"><?= esc($activity['label']) ?></p>
                        <p class="font-data mt-1 text-sm text-base-content/70"><?= esc($activity['reference']) ?></p>
                    </div>
                    <time class="font-data text-sm text-base-content/70"><?= esc($activity['occurredAt']) ?></time>
                </li>
            <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</section>
<?php endif ?>
</div>

<?= $this->endSection() ?>
