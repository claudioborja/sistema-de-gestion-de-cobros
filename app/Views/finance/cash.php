<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$active = $cash['active'] ?? null;
$registers = $cash['registers'] ?? [];
$movements = $cash['movements'] ?? [];
$incoming = $cash['incoming'] ?? [];
$history = $cash['history'] ?? [];
$movementLabels = [
    'APORTE' => ['Aporte', 'badge-info'],
    'COBRO' => ['Cobro', 'badge-success'],
    'RETIRO' => ['Retiro', 'badge-warning'],
    'GASTO' => ['Gasto', 'badge-warning'],
    'DEVOLUCION' => ['Devolución', 'badge-error'],
];
$oldRegister = old('caja_id', '', false);
$oldFund = old('fondo_inicial', '0.00', false);
?>

<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'primaryAction' => $active && $canCreateMovement ? [
        'label' => 'Registrar movimiento',
        'url' => site_url('turnos/' . (int) $active['id'] . '/movimientos/nuevo'),
        'icon' => 'plus',
    ] : null,
], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div id="form-errors" class="alert alert-error mb-6" role="alert" tabindex="-1" aria-labelledby="form-errors-title">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <div>
        <h2 id="form-errors-title" class="font-semibold">No se pudo abrir el turno</h2>
        <ul class="mt-1 list-disc pl-5">
            <?php foreach ($errors as $key => $error): ?>
                <li><?php if (in_array($key, ['caja_id', 'fondo_inicial'], true)): ?><a class="underline" href="#<?= esc($key, 'attr') ?>"><?= esc($error) ?></a><?php else: ?><?= esc($error) ?><?php endif ?></li>
            <?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>

<?php if ($incoming && $canHandoff): ?>
<section class="card card-border mb-6 bg-base-100" aria-labelledby="incoming-handoffs-title">
    <div class="card-body p-0">
        <header class="section-heading"><div><p class="eyebrow">Requiere tu acción</p><h2 id="incoming-handoffs-title" class="card-title mt-1">Recepciones pendientes</h2><p class="mt-1 text-sm text-base-content/70">Cuenta físicamente el efectivo antes de confirmar cada entrega.</p></div><span class="badge badge-warning badge-soft"><span class="font-data"><?= count($incoming) ?></span>&nbsp; pendientes</span></header>
        <ul class="divide-y divide-base-300" aria-label="Entregas pendientes de recepción">
        <?php foreach ($incoming as $handoff): ?>
            <li class="record-card flex-col items-stretch gap-4 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1"><p class="font-medium"><?= esc($handoff['caja_codigo'] . ' — ' . $handoff['caja_nombre']) ?> · Turno <span class="font-data">#<?= (int) $handoff['turno_id'] ?></span></p><p class="mt-1 text-sm text-base-content/70">Entrega de <?= esc($handoff['entregado_por_nombre']) ?> · <span class="font-data"><?= esc($handoff['entregado_en_local']) ?></span></p></div>
                <div class="flex flex-wrap items-center justify-between gap-3 sm:justify-end"><div class="text-right"><p class="font-data font-semibold">$<?= esc($handoff['importe_entregado']) ?></p><p class="text-xs text-base-content/60">Fondo: $<?= esc($handoff['fondo_remanente']) ?></p></div><a class="btn btn-primary btn-sm" href="<?= site_url('turnos/' . (int) $handoff['turno_id'] . '/entregar') ?>"><i data-lucide="package-check" class="icon" aria-hidden="true"></i>Revisar y recibir</a></div>
            </li>
        <?php endforeach ?>
        </ul>
    </div>
</section>
<?php endif ?>

<?php if (!$active): ?>
<section class="grid gap-6 lg:grid-cols-12" aria-labelledby="open-shift-title">
    <form method="post" action="<?= site_url('mi-caja/abrir') ?>" class="card card-border bg-base-100 lg:col-span-8">
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
            <fieldset class="fieldset">
                <legend id="open-shift-title" class="fieldset-legend text-lg">Abrir turno</legend>
                <p class="mb-4 max-w-2xl text-sm text-base-content/70">Selecciona el punto físico y registra el efectivo contado antes de iniciar operaciones.</p>
                <?php if ($registers): ?>
                <div class="form-grid">
                    <label class="form-field" for="caja_id">
                        <span>Caja disponible *</span>
                        <select class="select w-full <?= isset($errors['caja_id']) ? 'select-error' : '' ?>" id="caja_id" name="caja_id" required<?= isset($errors['caja_id']) ? ' aria-invalid="true" aria-describedby="caja_id-error"' : '' ?>>
                            <option value="">Selecciona una caja</option>
                            <?php foreach ($registers as $register): ?>
                            <option value="<?= (int) $register['id'] ?>" <?= (string) $oldRegister === (string) $register['id'] ? 'selected' : '' ?>><?= esc($register['codigo'] . ' — ' . $register['nombre']) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?php if (isset($errors['caja_id'])): ?><span class="field-error" id="caja_id-error"><?= esc($errors['caja_id']) ?></span><?php endif ?>
                    </label>
                    <label class="form-field" for="fondo_inicial">
                        <span>Fondo inicial *</span>
                        <input class="input w-full font-data <?= isset($errors['fondo_inicial']) ? 'input-error' : '' ?>" id="fondo_inicial" name="fondo_inicial" type="number" min="0" step="0.01" inputmode="decimal" value="<?= esc((string) $oldFund, 'attr') ?>" required aria-describedby="fondo_inicial-help<?= isset($errors['fondo_inicial']) ? ' fondo_inicial-error' : '' ?>"<?= isset($errors['fondo_inicial']) ? ' aria-invalid="true"' : '' ?>>
                        <span id="fondo_inicial-help" class="text-sm text-base-content/70">Incluye únicamente el efectivo disponible al abrir.</span>
                        <?php if (isset($errors['fondo_inicial'])): ?><span class="field-error" id="fondo_inicial-error"><?= esc($errors['fondo_inicial']) ?></span><?php endif ?>
                    </label>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i data-lucide="wallet" class="icon" aria-hidden="true"></i>Abrir turno</button></div>
                <?php else: ?>
                <?= view('partials/empty_state', [
                    'icon' => 'wallet',
                    'title' => 'No hay cajas disponibles',
                    'description' => 'Todas las cajas activas están ocupadas o todavía no se ha creado una caja física.',
                ], ['saveData' => false]) ?>
                <?php endif ?>
            </fieldset>
        </div>
    </form>
    <aside class="card card-border bg-base-100 lg:col-span-4">
        <div class="card-body">
            <h2 class="card-title">Antes de abrir</h2>
            <ul class="mt-2 space-y-3 text-sm text-base-content/70">
                <li class="flex gap-2"><i data-lucide="circle-check" class="icon shrink-0 text-success" aria-hidden="true"></i><span>Cuenta el fondo físico y registra el valor exacto.</span></li>
                <li class="flex gap-2"><i data-lucide="circle-check" class="icon shrink-0 text-success" aria-hidden="true"></i><span>Un usuario no puede operar dos turnos al mismo tiempo.</span></li>
                <li class="flex gap-2"><i data-lucide="circle-check" class="icon shrink-0 text-success" aria-hidden="true"></i><span>Una caja ocupada queda reservada hasta su cierre.</span></li>
            </ul>
        </div>
    </aside>
</section>
<?php else: ?>
<section class="card card-border bg-base-100" aria-labelledby="current-shift-title">
    <div class="card-body">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="eyebrow">Turno actual</p>
                <h2 id="current-shift-title" class="card-title mt-1"><?= esc($active['caja_codigo'] . ' — ' . $active['caja_nombre']) ?></h2>
                <p class="mt-1 text-sm text-base-content/70">Abierto el <span class="font-data"><?= esc($active['abierto_en_local']) ?></span> · Turno <span class="font-data">#<?= (int) $active['id'] ?></span></p>
            </div>
            <span class="badge badge-success badge-soft"><i data-lucide="circle-check" class="icon" aria-hidden="true"></i>Abierto</span>
        </div>
        <div class="stats stats-vertical mt-6 border border-base-300 sm:stats-horizontal">
            <div class="stat"><div class="stat-title">Fondo inicial</div><div class="stat-value font-data text-2xl">$<?= esc($active['fondo_inicial']) ?></div></div>
            <div class="stat"><div class="stat-title">Cobros en efectivo</div><div class="stat-value font-data text-2xl">$<?= esc($active['cobros_efectivo']) ?></div><div class="stat-desc"><?= (int) $active['movimientos_total'] ?> movimientos registrados</div></div>
            <div class="stat"><div class="stat-title">Efectivo esperado</div><div class="stat-value font-data text-2xl">$<?= esc($active['efectivo_esperado']) ?></div><div class="stat-desc">Entradas menos salidas</div></div>
        </div>
        <div class="alert alert-info alert-soft mt-6" role="status"><i data-lucide="info" class="icon" aria-hidden="true"></i><span>Los aportes, retiros y gastos forman el arqueo. Verifica los movimientos antes de contar y cerrar la caja.</span></div>
        <?php if ($canCloseShift): ?><div class="card-actions mt-4 justify-end"><a class="btn btn-error btn-soft" href="<?= site_url('turnos/' . (int) $active['id'] . '/cerrar') ?>"><i data-lucide="check-check" class="icon" aria-hidden="true"></i>Cerrar turno</a></div><?php endif ?>
    </div>
</section>

<section class="card card-border mt-6 bg-base-100" aria-labelledby="cash-movements-title">
    <div class="card-body p-0">
        <header class="section-heading"><div><h2 id="cash-movements-title" class="card-title">Movimientos del turno</h2><p class="mt-1 text-sm text-base-content/70">Entradas y salidas que forman el efectivo esperado.</p></div><span class="badge badge-ghost"><span class="font-data"><?= count($movements) ?></span>&nbsp; registros</span></header>
        <?php if (!$movements): ?>
            <?= view('partials/empty_state', ['icon' => 'receipt', 'title' => 'El turno no tiene movimientos', 'description' => 'El fondo inicial permanece como efectivo esperado hasta registrar una entrada o salida.'], ['saveData' => false]) ?>
        <?php else: ?>
        <div class="hidden overflow-x-auto md:block"><table class="table"><caption class="sr-only">Movimientos del turno de caja actual</caption><thead><tr><th scope="col">Tipo</th><th scope="col">Concepto</th><th scope="col">Fecha</th><th scope="col" class="text-right">Importe</th></tr></thead><tbody>
        <?php foreach ($movements as $movement): $meta = $movementLabels[$movement['tipo']] ?? [$movement['tipo'], 'badge-ghost']; ?>
        <tr><td><span class="badge badge-soft <?= esc($meta[1], 'attr') ?>"><?= esc($meta[0]) ?></span></td><td><?= esc($movement['concepto']) ?><?php if ($movement['pago_id'] !== null): ?> <a class="link" href="<?= site_url('pagos/' . (int) $movement['pago_id']) ?>">Cobro #<?= (int) $movement['pago_id'] ?></a><?php endif ?></td><td class="font-data"><?= esc($movement['registrado_en_local']) ?></td><td class="font-data text-right">$<?= esc($movement['importe']) ?></td></tr>
        <?php endforeach ?>
        </tbody></table></div>
        <ul class="divide-y divide-base-300 md:hidden" aria-label="Movimientos del turno">
        <?php foreach ($movements as $movement): $meta = $movementLabels[$movement['tipo']] ?? [$movement['tipo'], 'badge-ghost']; ?>
        <li class="record-card items-start"><div class="min-w-0"><span class="badge badge-soft <?= esc($meta[1], 'attr') ?>"><?= esc($meta[0]) ?></span><p class="mt-2 break-words font-medium"><?= esc($movement['concepto']) ?></p><p class="font-data mt-1 text-sm text-base-content/70"><?= esc($movement['registrado_en_local']) ?></p></div><p class="font-data shrink-0 font-semibold">$<?= esc($movement['importe']) ?></p></li>
        <?php endforeach ?>
        </ul>
        <?php endif ?>
    </div>
</section>
<?php endif ?>

<?php if ($history): ?>
<section class="card card-border mt-6 bg-base-100" aria-labelledby="cash-history-title">
    <div class="card-body p-0">
        <header class="section-heading"><div><h2 id="cash-history-title" class="card-title">Turnos recientes</h2><p class="mt-1 text-sm text-base-content/70">Últimos cierres registrados por tu usuario.</p></div></header>
        <div class="overflow-x-auto"><table class="table"><caption class="sr-only">Historial reciente de turnos cerrados</caption><thead><tr><th scope="col">Turno</th><th scope="col">Caja</th><th scope="col">Apertura</th><th scope="col">Cierre</th><th scope="col" class="text-right">Contado</th><th scope="col" class="text-right">Diferencia</th><?php if ($canHandoff): ?><th scope="col">Entrega</th><?php endif ?></tr></thead><tbody>
        <?php foreach ($history as $shift): ?>
        <tr><th scope="row" class="font-data">#<?= (int) $shift['id'] ?></th><td><?= esc($shift['caja_codigo'] . ' — ' . $shift['caja_nombre']) ?></td><td class="font-data"><?= esc($shift['abierto_en_local']) ?></td><td class="font-data"><?= esc($shift['cerrado_en_local']) ?></td><td class="font-data text-right">$<?= esc($shift['efectivo_contado']) ?></td><td class="font-data text-right"><?= (float) $shift['diferencia'] >= 0 ? '+' : '−' ?>$<?= esc(number_format(abs((float) $shift['diferencia']), 2, '.', '')) ?></td><?php if ($canHandoff): ?><td><a class="btn btn-ghost btn-sm" href="<?= site_url('turnos/' . (int) $shift['id'] . '/entregar') ?>"><?php if ($shift['entrega_id'] === null): ?><i data-lucide="send" class="icon" aria-hidden="true"></i>Entregar<?php elseif ($shift['entrega_estado'] === 'RECIBIDA'): ?><i data-lucide="circle-check" class="icon text-success" aria-hidden="true"></i>Recibida<?php else: ?><i data-lucide="clock-3" class="icon text-warning" aria-hidden="true"></i>Pendiente<?php endif ?></a></td><?php endif ?></tr>
        <?php endforeach ?>
        </tbody></table></div>
    </div>
</section>
<?php endif ?>

<?= $this->endSection() ?>
