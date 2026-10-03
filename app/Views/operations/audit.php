<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', ['context' => 'Reportes y operación', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>

<nav class="mb-6 overflow-x-auto" aria-label="Navegación de operación"><div class="tabs tabs-box w-max min-w-full sm:min-w-0"><a class="tab" href="<?= site_url('operacion/estado') ?>"><i data-lucide="activity" class="icon" aria-hidden="true"></i>Estado</a><a class="tab" href="<?= site_url('operacion/contingencias') ?>"><i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i>Contingencias</a><a class="tab tab-active" aria-current="page" href="<?= site_url('operacion/auditoria') ?>"><i data-lucide="history" class="icon" aria-hidden="true"></i>Auditoría</a></div></nav>

<form method="get" class="filter-toolbar mb-6 rounded-box border border-base-300 bg-base-100" aria-label="Filtrar auditoría" data-datatable-filter="audit">
    <label class="input min-w-0 flex-1"><i data-lucide="search" class="icon" aria-hidden="true"></i><input name="q" value="<?= esc($queryText, 'attr') ?>" placeholder="Registro, acción o usuario…" aria-label="Buscar eventos" maxlength="100" autocomplete="off"></label>
    <select class="select" name="accion" aria-label="Tipo de acción"><option value="">Todas las acciones</option><?php foreach ($actions as $key => $label): ?><option value="<?= esc($key, 'attr') ?>" <?= $action === $key ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?></select>
    <button class="btn" type="submit">Filtrar</button>
    <?php if ($canClearAudit): ?><button class="btn btn-error btn-soft" type="submit" form="audit-clear-form" data-confirm="Esta acción eliminará permanentemente todos los eventos de auditoría. No se puede deshacer."><i data-lucide="trash-2" class="icon" aria-hidden="true"></i>Limpiar logs</button><?php endif ?>
</form>
<?php if ($canClearAudit): ?><form id="audit-clear-form" method="post" action="<?= site_url('operacion/auditoria/limpiar') ?>" class="hidden"><?= csrf_field() ?></form><?php endif ?>

<section class="card card-border bg-base-100" aria-labelledby="audit-title">
    <div class="card-body p-0"><header class="section-heading"><div><h2 id="audit-title" class="card-title">Eventos recientes</h2><p class="mt-1 text-sm text-base-content/70"><span class="font-data"><?= $total ?></span> registros encontrados, del más reciente al más antiguo.</p></div></header>
    <?php if (!$events): ?>
        <?= view('partials/empty_state', ['icon' => 'history', 'title' => 'Aún no hay eventos', 'description' => 'Las acciones administrativas aparecerán aquí cuando se registren.'], ['saveData' => false]) ?>
    <?php else: ?>
        <div class="hidden overflow-x-auto md:block"><table class="table" data-datatable="audit" data-source="<?= site_url('operacion/auditoria/datos') ?>"><caption class="sr-only">Bitácora de eventos administrativos</caption><thead><tr><th scope="col">Fecha y hora</th><th scope="col">Acción</th><th scope="col">Registro</th><th scope="col">Usuario</th><th scope="col" class="dt-actions"><span class="sr-only">Detalles</span></th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td class="font-data whitespace-nowrap"><?= esc($event['occurredAt']) ?></td><td><?= esc($event['action']) ?></td><td class="break-words font-medium"><?= esc($event['reference']) ?></td><td><?= esc($event['actor']) ?></td><td class="text-right"><button class="btn btn-sm" type="button" aria-haspopup="dialog" data-audit-detail data-audit-id="<?= (int) $event['eventId'] ?>" data-audit-date="<?= esc($event['occurredAt'], 'attr') ?>" data-audit-action="<?= esc($event['action'], 'attr') ?>" data-audit-reference="<?= esc($event['reference'], 'attr') ?>" data-audit-actor="<?= esc($event['actor'], 'attr') ?>" data-audit-details="<?= esc($event['details'], 'attr') ?>"><i data-lucide="eye" class="icon" aria-hidden="true"></i>Detalles</button></td></tr><?php endforeach ?></tbody></table></div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Bitácora de eventos administrativos"><?php foreach ($events as $event): ?><li class="record-card"><div class="min-w-0"><p class="font-semibold"><?= esc($event['action']) ?></p><p class="mt-1 break-words"><?= esc($event['reference']) ?></p><p class="mt-2 text-sm text-base-content/70"><?= esc($event['actor']) ?></p><button class="btn btn-sm mt-3" type="button" aria-haspopup="dialog" data-audit-detail data-audit-id="<?= (int) $event['eventId'] ?>" data-audit-date="<?= esc($event['occurredAt'], 'attr') ?>" data-audit-action="<?= esc($event['action'], 'attr') ?>" data-audit-reference="<?= esc($event['reference'], 'attr') ?>" data-audit-actor="<?= esc($event['actor'], 'attr') ?>" data-audit-details="<?= esc($event['details'], 'attr') ?>"><i data-lucide="eye" class="icon" aria-hidden="true"></i>Detalles</button></div><time class="font-data whitespace-nowrap text-sm text-base-content/70"><?= esc($event['occurredAt']) ?></time></li><?php endforeach ?></ul>
    <?php endif ?>
    <div class="datatable-fallback-pagination"><?= view('partials/pagination', ['total' => $total, 'page' => $page, 'pageSize' => 5, 'base' => 'operacion/auditoria', 'params' => ['q' => $queryText, 'accion' => $action]], ['saveData' => false]) ?></div>
    </div>
</section>

<dialog id="audit-detail-modal" class="modal">
    <div class="modal-box max-w-2xl">
        <div class="flex items-start justify-between gap-4"><div><p id="audit-detail-id" class="font-data text-sm text-base-content/70"></p><h2 class="mt-1 text-xl font-semibold">Detalle del evento</h2></div><form method="dialog"><button class="btn btn-ghost btn-square" type="submit" aria-label="Cerrar detalle"><i data-lucide="x" class="icon" aria-hidden="true"></i></button></form></div>
        <dl class="mt-5 grid gap-4 border-y border-base-300 py-4 sm:grid-cols-2"><div><dt class="text-sm text-base-content/70">Fecha y hora</dt><dd id="audit-detail-date" class="font-data mt-1"></dd></div><div><dt class="text-sm text-base-content/70">Usuario</dt><dd id="audit-detail-actor" class="mt-1 font-medium"></dd></div><div><dt class="text-sm text-base-content/70">Acción</dt><dd id="audit-detail-action" class="mt-1 font-medium"></dd></div><div><dt class="text-sm text-base-content/70">Registro</dt><dd id="audit-detail-reference" class="font-data mt-1 break-words"></dd></div></dl>
        <div class="mt-5"><h3 class="font-semibold">Datos registrados</h3><pre id="audit-detail-data" class="font-data mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-box border border-base-300 bg-base-200 p-4 text-sm"></pre></div>
        <div class="modal-action"><form method="dialog"><button class="btn" type="submit">Cerrar</button></form></div>
    </div>
    <form method="dialog" class="modal-backdrop"><button type="submit" aria-label="Cerrar detalle">Cerrar</button></form>
</dialog>

<?= $this->endSection() ?>
