<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$mode = $mode ?? 'detail';
$errors = session('errors') ?? [];
$statusClass = static fn (string $status): string => match ($status) {
    'PAGADA', 'VIGENTE' => 'badge-success badge-soft',
    'VENCIDA', 'REVERTIDA' => 'badge-error badge-soft',
    default => 'badge-warning badge-soft',
};
$displayDate = static function (?string $value, bool $withTime = false): string {
    if (!$value) return 'Sin fecha';
    try {
        $date = $withTime
            ? (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Guayaquil'))
            : new DateTimeImmutable($value);
        return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    } catch (Throwable) {
        return $value;
    }
};
$pendingInstallments = array_values(array_filter(
    $obligation['cuotas'],
    static fn (array $installment): bool => $installment['estado'] !== 'PAGADA'
));
$today = (new DateTimeImmutable('now', new DateTimeZone('America/Guayaquil')))->format('Y-m-d');
?>

<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $obligation['cliente'], 'url' => site_url('clientes/' . (int) $obligation['cliente_id'] . '/cartera')],
        ['label' => 'Obligación #' . (int) $obligation['id']],
    ],
], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div id="validation-errors" class="alert alert-error mb-5" role="alert" tabindex="-1">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <span><?= esc(implode(' ', array_unique(array_values($errors)))) ?></span>
</div>
<?php endif ?>

<?php if ($mode === 'reprogram'): ?>
<div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <form method="post" action="<?= site_url('obligaciones/' . (int) $obligation['id'] . '/reprogramaciones') ?>" class="card card-border min-w-0 bg-base-100">
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_key" value="<?= esc((string) old('idempotency_key', $idempotencyKey), 'attr') ?>">
            <div><h2 class="card-title">Nuevo cronograma</h2><p class="mt-1 text-sm text-base-content/70">Cada cuota conserva su importe y sus pagos. Solo cambia la fecha de vencimiento.</p></div>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <?php foreach ($pendingInstallments as $installment): ?>
                <fieldset class="fieldset rounded-box border border-base-300 p-4">
                    <legend class="fieldset-legend">Cuota #<?= (int) $installment['numero_cuota'] ?></legend>
                    <dl class="mb-2 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-base-content/70">Fecha actual</dt><dd class="font-data"><?= esc($displayDate($installment['fecha_vencimiento'])) ?></dd></div><div><dt class="text-base-content/70">Saldo</dt><dd class="font-data font-semibold">$<?= esc($installment['saldo']) ?></dd></div></dl>
                    <label for="due-date-<?= (int) $installment['id'] ?>">Nueva fecha</label>
                    <input id="due-date-<?= (int) $installment['id'] ?>" class="input w-full <?= isset($errors['due_dates']) ? 'input-error' : '' ?>" type="date" name="due_dates[<?= (int) $installment['id'] ?>]" value="<?= esc((string) old('due_dates.' . (int) $installment['id'], $installment['fecha_vencimiento']), 'attr') ?>" min="<?= esc($today, 'attr') ?>" required <?= isset($errors['due_dates']) ? 'aria-describedby="due-dates-error" aria-invalid="true"' : '' ?>>
                </fieldset>
                <?php endforeach ?>
            </div>
            <?php if (isset($errors['due_dates'])): ?><p id="due-dates-error" class="mt-2 text-sm text-error"><?= esc($errors['due_dates']) ?></p><?php endif ?>
            <fieldset class="fieldset mt-3">
                <legend class="fieldset-legend">Motivo de la reprogramación</legend>
                <label class="sr-only" for="reprogram-reason">Motivo de la reprogramación</label>
                <textarea id="reprogram-reason" class="textarea min-h-28 w-full <?= isset($errors['reason']) ? 'textarea-error' : '' ?>" name="reason" minlength="10" maxlength="500" required placeholder="Explica el acuerdo o la causa del cambio de fechas." <?= isset($errors['reason']) ? 'aria-describedby="reason-help reason-error" aria-invalid="true"' : 'aria-describedby="reason-help"' ?>><?= esc((string) old('reason')) ?></textarea>
                <?php if (isset($errors['reason'])): ?><p id="reason-error" class="text-sm text-error"><?= esc($errors['reason']) ?></p><?php endif ?>
                <p id="reason-help" class="label">Entre 10 y 500 caracteres. Quedará registrado en la auditoría.</p>
            </fieldset>
            <div class="card-actions flex-col-reverse sm:flex-row sm:justify-end">
                <a class="btn w-full sm:w-auto" href="<?= site_url('obligaciones/' . (int) $obligation['id']) ?>">Cancelar</a>
                <button class="btn btn-primary w-full sm:w-auto" type="submit" data-confirm="Se creará una nueva versión del cronograma sin modificar los pagos existentes."><i data-lucide="calendar-check" class="icon" aria-hidden="true"></i>Guardar reprogramación</button>
            </div>
        </div>
    </form>
    <aside class="grid content-start gap-5">
        <section class="card card-border bg-base-100"><div class="card-body">
            <h2 class="card-title">Resumen</h2>
            <dl class="grid gap-3 text-sm"><div><dt class="text-base-content/70">Cliente</dt><dd class="font-semibold"><?= esc($obligation['cliente']) ?></dd></div><div><dt class="text-base-content/70">Saldo vigente</dt><dd class="font-data text-lg font-semibold">$<?= esc($obligation['saldo']) ?></dd></div><div><dt class="text-base-content/70">Cuotas por actualizar</dt><dd class="font-data"><?= count($pendingInstallments) ?></dd></div></dl>
        </div></section>
        <div class="alert alert-info alert-soft" role="status"><i data-lucide="shield-check" class="icon" aria-hidden="true"></i><span>El historial anterior permanece disponible y las aplicaciones de pago no se alteran.</span></div>
    </aside>
</div>
<?php else: ?>
<div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-5">
        <section class="card card-border bg-base-100"><div class="card-body">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="card-title"><?= esc($obligation['concepto']) ?></h2><p class="mt-1 text-sm text-base-content/70"><?= esc($obligation['tipo']) ?> <?= esc($obligation['numero_completo'] ?: '#' . $obligation['id']) ?> · originada el <?= esc($displayDate($obligation['fecha_origen'])) ?></p></div><span class="badge <?= esc($statusClass($obligation['estado']), 'attr') ?>"><?= esc($obligation['estado']) ?></span></div>
            <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4"><div><dt class="text-sm text-base-content/70">Importe original</dt><dd class="font-data text-lg font-semibold">$<?= esc($obligation['importe_base']) ?></dd></div><div><dt class="text-sm text-base-content/70">Saldo</dt><dd class="font-data text-lg font-semibold">$<?= esc($obligation['saldo']) ?></dd></div><div><dt class="text-sm text-base-content/70">Próximo vencimiento</dt><dd class="font-data text-lg font-semibold"><?= esc($displayDate($obligation['vencimiento'])) ?></dd></div><div><dt class="text-sm text-base-content/70">Cuotas</dt><dd class="font-data text-lg font-semibold"><?= count($obligation['cuotas']) ?></dd></div></dl>
        </div></section>

        <section class="card card-border bg-base-100"><div class="card-body p-0">
            <header class="section-heading"><div><h2 class="card-title">Cronograma vigente</h2><p class="mt-1 text-sm text-base-content/70">Importes aplicados y saldo derivado por cuota.</p></div></header>
            <div class="overflow-x-auto"><table class="table"><thead><tr><th scope="col">Cuota</th><th scope="col">Vencimiento</th><th scope="col">Programado</th><th scope="col">Aplicado</th><th scope="col">Saldo</th><th scope="col">Estado</th></tr></thead><tbody>
                <?php foreach ($obligation['cuotas'] as $installment): ?><tr><th scope="row">#<?= (int) $installment['numero_cuota'] ?></th><td class="font-data"><?= esc($displayDate($installment['fecha_vencimiento'])) ?></td><td class="font-data">$<?= esc($installment['importe_programado']) ?></td><td class="font-data">$<?= esc($installment['aplicado']) ?></td><td class="font-data font-semibold">$<?= esc($installment['saldo']) ?></td><td><span class="badge <?= esc($statusClass($installment['estado']), 'attr') ?>"><?= esc($installment['estado']) ?></span></td></tr><?php endforeach ?>
            </tbody></table></div>
        </div></section>

        <section class="card card-border bg-base-100"><div class="card-body p-0">
            <header class="section-heading"><div><h2 class="card-title">Aplicaciones de pago</h2><p class="mt-1 text-sm text-base-content/70">Movimientos que han afectado esta obligación.</p></div></header>
            <?php if (!$obligation['aplicaciones']): ?><div class="px-6 pb-6 text-sm text-base-content/70">Todavía no existen pagos aplicados.</div><?php else: ?>
            <div class="overflow-x-auto"><table class="table"><thead><tr><th scope="col">Pago</th><th scope="col">Fecha</th><th scope="col">Cuota</th><th scope="col">Importe</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead><tbody>
                <?php foreach ($obligation['aplicaciones'] as $application): ?><tr><th scope="row" class="font-data">#<?= (int) $application['pago_id'] ?></th><td class="font-data"><?= esc($displayDate($application['fecha_declarada'])) ?></td><td>#<?= (int) $application['numero_cuota'] ?></td><td class="font-data">$<?= esc($application['importe']) ?></td><td><span class="badge <?= esc($statusClass($application['estado']), 'attr') ?>"><?= esc($application['estado']) ?></span></td><td class="text-right"><a class="btn btn-sm" href="<?= site_url('pagos/' . (int) $application['pago_id']) ?>">Ver pago</a></td></tr><?php endforeach ?>
            </tbody></table></div><?php endif ?>
        </div></section>

        <?php if (count($obligation['versiones']) > count($obligation['cuotas'])): ?>
        <section class="card card-border bg-base-100"><div class="card-body p-0">
            <header class="section-heading"><div><h2 class="card-title">Historial del cronograma</h2><p class="mt-1 text-sm text-base-content/70">Versiones conservadas para trazabilidad.</p></div></header>
            <div class="overflow-x-auto"><table class="table table-sm"><thead><tr><th scope="col">Versión</th><th scope="col">Cuota</th><th scope="col">Vencimiento</th><th scope="col">Importe</th><th scope="col">Registrada</th><th scope="col">Motivo</th></tr></thead><tbody>
                <?php foreach ($obligation['versiones'] as $version): ?><tr><th scope="row">v<?= (int) $version['version'] ?></th><td>#<?= (int) $version['numero_cuota'] ?></td><td class="font-data"><?= esc($displayDate($version['fecha_vencimiento'])) ?></td><td class="font-data">$<?= esc($version['importe_programado']) ?></td><td class="font-data"><?= esc($displayDate($version['efectiva_en'], true)) ?></td><td><?= esc($version['motivo'] ?: ((int) $version['version'] === 1 ? 'Cronograma inicial' : 'Sin motivo')) ?></td></tr><?php endforeach ?>
            </tbody></table></div>
        </div></section>
        <?php endif ?>
    </div>

    <aside class="grid content-start gap-3">
        <?php if ($canReprogram): ?><a class="btn btn-primary" href="<?= site_url('obligaciones/' . (int) $obligation['id'] . '/reprogramar') ?>"><i data-lucide="calendar-clock" class="icon" aria-hidden="true"></i>Reprogramar</a><?php endif ?>
        <?php if ($obligation['estado'] !== 'PAGADA' && $obligation['estado'] !== 'BORRADOR'): ?><a class="btn" href="<?= site_url('clientes/' . (int) $obligation['cliente_id'] . '/pagos/nuevo') ?>"><i data-lucide="plus" class="icon" aria-hidden="true"></i>Registrar cobro</a><?php endif ?>
        <a class="btn" href="<?= site_url('documentos/' . (int) $obligation['id']) ?>"><i data-lucide="file-text" class="icon" aria-hidden="true"></i>Ver documento</a>
        <a class="btn btn-ghost" href="<?= site_url('clientes/' . (int) $obligation['cliente_id'] . '/cartera') ?>"><i data-lucide="arrow-left" class="icon" aria-hidden="true"></i>Volver a cartera</a>
    </aside>
</div>
<?php endif ?>

<?= $this->endSection() ?>
