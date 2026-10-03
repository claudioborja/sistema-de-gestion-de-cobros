<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static function (string $key, string $default = ''): string {
    $input = old($key, $default, false);
    return is_scalar($input) ? (string) $input : '';
};
$hasHandoff = $shift['entrega_id'] !== null;
$isReceived = ($shift['entrega_estado'] ?? null) === 'RECIBIDA';
$difference = (float) $shift['diferencia'];
?>

<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => $breadcrumbs,
], ['saveData' => false]) ?>

<?php if ($errors): ?>
<div id="form-errors" class="alert alert-error mb-6" role="alert" tabindex="-1" aria-labelledby="form-errors-title">
    <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
    <div>
        <h2 id="form-errors-title" class="font-semibold"><?= $hasHandoff ? 'No se pudo confirmar la recepción' : 'No se pudo registrar la entrega' ?></h2>
        <ul class="mt-1 list-disc pl-5">
        <?php foreach ($errors as $key => $error): ?>
            <li><?php if (in_array($key, ['recibido_por', 'fondo_remanente', 'observacion_entrega', 'observacion_recepcion'], true)): ?><a class="underline" href="#<?= esc($key, 'attr') ?>"><?= esc($error) ?></a><?php else: ?><?= esc($error) ?><?php endif ?></li>
        <?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>

<section class="grid gap-6 lg:grid-cols-12">
    <div class="lg:col-span-8">
        <?php if (!$hasHandoff && $shift['can_deliver']): ?>
        <form method="post" action="<?= site_url('turnos/' . (int) $shift['id'] . '/entregar') ?>" class="card card-border bg-base-100">
            <div class="card-body">
                <?= csrf_field() ?>
                <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
                <fieldset class="fieldset">
                    <legend class="fieldset-legend text-lg">Datos de la entrega</legend>
                    <p class="mb-4 max-w-2xl text-sm text-base-content/70">Selecciona quién contará y confirmará la recepción. El sistema calcula el importe entregado restando el fondo remanente al efectivo del cierre.</p>
                    <?php if ($shift['recipients']): ?>
                    <div class="form-grid">
                        <label class="form-field" for="recibido_por">
                            <span>Recibe *</span>
                            <select class="select w-full <?= isset($errors['recibido_por']) ? 'select-error' : '' ?>" id="recibido_por" name="recibido_por" required<?= isset($errors['recibido_por']) ? ' aria-invalid="true" aria-describedby="recibido_por-error"' : '' ?>>
                                <option value="">Selecciona una persona</option>
                                <?php foreach ($shift['recipients'] as $recipient): ?>
                                <option value="<?= (int) $recipient['id'] ?>" <?= $value('recibido_por') === (string) $recipient['id'] ? 'selected' : '' ?>><?= esc($recipient['username']) ?></option>
                                <?php endforeach ?>
                            </select>
                            <?php if (isset($errors['recibido_por'])): ?><span class="field-error" id="recibido_por-error"><?= esc($errors['recibido_por']) ?></span><?php endif ?>
                        </label>
                        <label class="form-field" for="fondo_remanente">
                            <span>Fondo remanente *</span>
                            <input class="input w-full font-data <?= isset($errors['fondo_remanente']) ? 'input-error' : '' ?>" id="fondo_remanente" name="fondo_remanente" type="number" min="0" max="<?= esc($shift['efectivo_contado'], 'attr') ?>" step="0.01" inputmode="decimal" value="<?= esc($value('fondo_remanente', '0.00'), 'attr') ?>" required aria-describedby="fondo_remanente-help<?= isset($errors['fondo_remanente']) ? ' fondo_remanente-error' : '' ?>"<?= isset($errors['fondo_remanente']) ? ' aria-invalid="true"' : '' ?>>
                            <span id="fondo_remanente-help" class="text-sm text-base-content/70">Debe permanecer físicamente en caja; no puede superar $<?= esc($shift['efectivo_contado']) ?>.</span>
                            <?php if (isset($errors['fondo_remanente'])): ?><span class="field-error" id="fondo_remanente-error"><?= esc($errors['fondo_remanente']) ?></span><?php endif ?>
                        </label>
                    </div>
                    <label class="form-field mt-4" for="observacion_entrega">
                        <span>Observación de entrega</span>
                        <textarea class="textarea min-h-28 w-full <?= isset($errors['observacion_entrega']) ? 'textarea-error' : '' ?>" id="observacion_entrega" name="observacion_entrega" maxlength="500" aria-describedby="observacion_entrega-help<?= isset($errors['observacion_entrega']) ? ' observacion_entrega-error' : '' ?>"<?= isset($errors['observacion_entrega']) ? ' aria-invalid="true"' : '' ?>><?= esc($value('observacion_entrega')) ?></textarea>
                        <span id="observacion_entrega-help" class="text-sm text-base-content/70">Opcional. Anota sobres, denominaciones o cualquier condición que deba comprobarse.</span>
                        <?php if (isset($errors['observacion_entrega'])): ?><span class="field-error" id="observacion_entrega-error"><?= esc($errors['observacion_entrega']) ?></span><?php endif ?>
                    </label>
                    <?php else: ?>
                    <div class="alert alert-warning alert-soft" role="alert"><i data-lucide="user-x" class="icon" aria-hidden="true"></i><span>No existe otra persona activa con permiso para recibir el turno.</span></div>
                    <?php endif ?>
                </fieldset>
                <div class="alert alert-info alert-soft mt-6" role="status"><i data-lucide="calculator" class="icon" aria-hidden="true"></i><span>La entrega quedará pendiente hasta que la persona seleccionada confirme los valores desde su sesión.</span></div>
                <div class="form-actions"><a class="btn" href="<?= site_url('mi-caja') ?>">Volver</a><?php if ($shift['recipients']): ?><button class="btn btn-primary" type="submit"><i data-lucide="send" class="icon" aria-hidden="true"></i>Registrar entrega</button><?php endif ?></div>
            </div>
        </form>
        <?php elseif ($hasHandoff): ?>
        <article class="card card-border bg-base-100" aria-labelledby="handoff-status-title">
            <div class="card-body">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div><p class="eyebrow">Entrega #<?= (int) $shift['entrega_id'] ?></p><h2 id="handoff-status-title" class="card-title mt-1"><?= $isReceived ? 'Recepción confirmada' : 'Pendiente de recepción' ?></h2></div>
                    <span class="badge badge-soft <?= $isReceived ? 'badge-success' : 'badge-warning' ?>"><i data-lucide="<?= $isReceived ? 'circle-check' : 'clock-3' ?>" class="icon" aria-hidden="true"></i><?= $isReceived ? 'Recibida' : 'Pendiente' ?></span>
                </div>
                <div class="stats stats-vertical mt-6 border border-base-300 sm:stats-horizontal">
                    <div class="stat"><div class="stat-title">Efectivo entregado</div><div class="stat-value font-data text-2xl">$<?= esc($shift['importe_entregado']) ?></div></div>
                    <div class="stat"><div class="stat-title">Fondo remanente</div><div class="stat-value font-data text-2xl">$<?= esc($shift['fondo_remanente']) ?></div></div>
                </div>
                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-base-content/60">Entregado por</dt><dd class="mt-1 font-medium"><?= esc($shift['entregado_por_nombre']) ?></dd></div>
                    <div><dt class="text-base-content/60">Asignado a</dt><dd class="mt-1 font-medium"><?= esc($shift['recibido_por_nombre']) ?></dd></div>
                    <div><dt class="text-base-content/60">Fecha de entrega</dt><dd class="font-data mt-1"><?= esc($shift['entregado_en_local']) ?></dd></div>
                    <div><dt class="text-base-content/60">Fecha de recepción</dt><dd class="font-data mt-1"><?= esc($shift['recibido_en_local'] ?? 'Pendiente') ?></dd></div>
                    <?php if ($shift['observacion_entrega'] !== null): ?><div class="sm:col-span-2"><dt class="text-base-content/60">Observación de entrega</dt><dd class="mt-1 whitespace-pre-line"><?= esc($shift['observacion_entrega']) ?></dd></div><?php endif ?>
                    <?php if ($shift['observacion_recepcion'] !== null): ?><div class="sm:col-span-2"><dt class="text-base-content/60">Observación de recepción</dt><dd class="mt-1 whitespace-pre-line"><?= esc($shift['observacion_recepcion']) ?></dd></div><?php endif ?>
                </dl>

                <?php if ($shift['can_receive']): ?>
                <form method="post" action="<?= site_url('turnos/' . (int) $shift['id'] . '/recibir') ?>" class="mt-6 border-t border-base-300 pt-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend text-lg">Confirmar recepción</legend>
                        <p class="mb-4 text-sm text-base-content/70">Cuenta el efectivo y comprueba por separado el fondo que permanece en caja antes de confirmar.</p>
                        <label class="form-field" for="observacion_recepcion">
                            <span>Observación de recepción</span>
                            <textarea class="textarea min-h-28 w-full <?= isset($errors['observacion_recepcion']) ? 'textarea-error' : '' ?>" id="observacion_recepcion" name="observacion_recepcion" maxlength="500" aria-describedby="observacion_recepcion-help<?= isset($errors['observacion_recepcion']) ? ' observacion_recepcion-error' : '' ?>"<?= isset($errors['observacion_recepcion']) ? ' aria-invalid="true"' : '' ?>><?= esc($value('observacion_recepcion')) ?></textarea>
                            <span id="observacion_recepcion-help" class="text-sm text-base-content/70">Opcional. Si existe una novedad, déjala registrada antes de confirmar.</span>
                            <?php if (isset($errors['observacion_recepcion'])): ?><span class="field-error" id="observacion_recepcion-error"><?= esc($errors['observacion_recepcion']) ?></span><?php endif ?>
                        </label>
                    </fieldset>
                    <div class="alert alert-warning alert-soft mt-6" role="alert"><i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i><span>Confirma únicamente después de recibir y contar físicamente ambos valores.</span></div>
                    <div class="form-actions"><a class="btn" href="<?= site_url('mi-caja') ?>">Volver</a><button class="btn btn-success" type="submit"><i data-lucide="check-check" class="icon" aria-hidden="true"></i>Confirmar recepción</button></div>
                </form>
                <?php else: ?>
                <div class="card-actions mt-6 justify-end"><a class="btn" href="<?= site_url('mi-caja') ?>">Volver a Mi caja</a></div>
                <?php endif ?>
            </div>
        </article>
        <?php else: ?>
        <div class="alert alert-warning" role="alert"><i data-lucide="shield-alert" class="icon" aria-hidden="true"></i><span>Solo la persona responsable del turno puede iniciar esta entrega.</span></div>
        <?php endif ?>
    </div>

    <aside class="card card-border bg-base-100 lg:col-span-4" aria-labelledby="handoff-summary-title">
        <div class="card-body">
            <p class="eyebrow">Cierre de origen</p>
            <h2 id="handoff-summary-title" class="card-title mt-1"><?= esc($shift['caja_codigo'] . ' — ' . $shift['caja_nombre']) ?></h2>
            <p class="text-sm text-base-content/70">Turno <span class="font-data">#<?= (int) $shift['id'] ?></span> de <?= esc($shift['propietario_nombre']) ?></p>
            <div class="stats stats-vertical mt-5 border border-base-300">
                <div class="stat"><div class="stat-title">Fondo inicial</div><div class="stat-value font-data text-2xl">$<?= esc($shift['fondo_inicial']) ?></div></div>
                <div class="stat"><div class="stat-title">Efectivo contado</div><div class="stat-value font-data text-2xl">$<?= esc($shift['efectivo_contado']) ?></div></div>
                <div class="stat"><div class="stat-title">Diferencia de arqueo</div><div class="stat-value font-data text-2xl <?= $difference === 0.0 ? '' : ($difference > 0 ? 'text-success' : 'text-error') ?>"><?= $difference > 0 ? '+' : ($difference < 0 ? '−' : '') ?>$<?= esc(number_format(abs($difference), 2, '.', '')) ?></div></div>
            </div>
            <p class="font-data mt-5 text-sm text-base-content/70">Cerrado el <?= esc($shift['cerrado_en_local']) ?></p>
        </div>
    </aside>
</section>

<?= $this->endSection() ?>
