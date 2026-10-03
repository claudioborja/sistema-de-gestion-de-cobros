<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static function (string $key, string $default = ''): string {
    $input = old($key, $default, false);
    return is_scalar($input) ? (string) $input : '';
};
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
        <h2 id="form-errors-title" class="font-semibold">No se pudo cerrar el turno</h2>
        <ul class="mt-1 list-disc pl-5">
        <?php foreach ($errors as $key => $error): ?>
            <li><?php if (in_array($key, ['efectivo_contado', 'motivo_diferencia'], true)): ?><a class="underline" href="#<?= esc($key, 'attr') ?>"><?= esc($error) ?></a><?php else: ?><?= esc($error) ?><?php endif ?></li>
        <?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>

<section class="grid gap-6 lg:grid-cols-12">
    <form method="post" action="<?= site_url('turnos/' . (int) $shift['id'] . '/cerrar') ?>" class="card card-border bg-base-100 lg:col-span-8">
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
            <fieldset class="fieldset">
                <legend class="fieldset-legend text-lg">Arqueo físico</legend>
                <p class="mb-4 max-w-2xl text-sm text-base-content/70">Cuenta todo el efectivo de la caja. El sistema calculará la diferencia al confirmar.</p>
                <label class="form-field" for="efectivo_contado">
                    <span>Efectivo contado *</span>
                    <input class="input w-full font-data <?= isset($errors['efectivo_contado']) ? 'input-error' : '' ?>" id="efectivo_contado" name="efectivo_contado" type="number" min="0" step="0.01" inputmode="decimal" value="<?= esc($value('efectivo_contado'), 'attr') ?>" required aria-describedby="efectivo_contado-help<?= isset($errors['efectivo_contado']) ? ' efectivo_contado-error' : '' ?>"<?= isset($errors['efectivo_contado']) ? ' aria-invalid="true"' : '' ?>>
                    <span id="efectivo_contado-help" class="text-sm text-base-content/70">Registra el valor físico, no copies el esperado sin realizar el conteo.</span>
                    <?php if (isset($errors['efectivo_contado'])): ?><span class="field-error" id="efectivo_contado-error"><?= esc($errors['efectivo_contado']) ?></span><?php endif ?>
                </label>
                <label class="form-field mt-4" for="motivo_diferencia">
                    <span>Explicación de diferencia</span>
                    <textarea class="textarea min-h-28 w-full <?= isset($errors['motivo_diferencia']) ? 'textarea-error' : '' ?>" id="motivo_diferencia" name="motivo_diferencia" maxlength="500" aria-describedby="motivo_diferencia-help<?= isset($errors['motivo_diferencia']) ? ' motivo_diferencia-error' : '' ?>"<?= isset($errors['motivo_diferencia']) ? ' aria-invalid="true"' : '' ?>><?= esc($value('motivo_diferencia')) ?></textarea>
                    <span id="motivo_diferencia-help" class="text-sm text-base-content/70">Es obligatoria si el conteo produce un faltante o sobrante.</span>
                    <?php if (isset($errors['motivo_diferencia'])): ?><span class="field-error" id="motivo_diferencia-error"><?= esc($errors['motivo_diferencia']) ?></span><?php endif ?>
                </label>
            </fieldset>
            <div class="alert alert-warning alert-soft mt-6" role="alert"><i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i><span>Al cerrar, el turno dejará de aceptar movimientos y la caja quedará disponible para otra apertura.</span></div>
            <div class="form-actions"><a class="btn" href="<?= site_url('mi-caja') ?>">Cancelar</a><button class="btn btn-error" type="submit"><i data-lucide="check-check" class="icon" aria-hidden="true"></i>Cerrar turno</button></div>
        </div>
    </form>

    <aside class="card card-border bg-base-100 lg:col-span-4" aria-labelledby="closing-summary-title">
        <div class="card-body">
            <p class="eyebrow">Resumen del sistema</p>
            <h2 id="closing-summary-title" class="card-title mt-1"><?= esc($shift['caja_codigo'] . ' — ' . $shift['caja_nombre']) ?></h2>
            <p class="text-sm text-base-content/70">Turno <span class="font-data">#<?= (int) $shift['id'] ?></span> · abierto el <span class="font-data"><?= esc($shift['abierto_en_local']) ?></span></p>
            <div class="stats stats-vertical mt-5 border border-base-300">
                <div class="stat"><div class="stat-title">Fondo inicial</div><div class="stat-value font-data text-2xl">$<?= esc($shift['fondo_inicial']) ?></div></div>
                <div class="stat"><div class="stat-title">Entradas</div><div class="stat-value font-data text-2xl">+$<?= esc($shift['entradas']) ?></div></div>
                <div class="stat"><div class="stat-title">Salidas</div><div class="stat-value font-data text-2xl">−$<?= esc($shift['salidas']) ?></div></div>
                <div class="stat"><div class="stat-title">Efectivo esperado</div><div class="stat-value font-data text-2xl">$<?= esc($shift['efectivo_esperado']) ?></div><div class="stat-desc"><?= (int) $shift['movimientos_total'] ?> movimientos</div></div>
            </div>
        </div>
    </aside>
</section>

<?= $this->endSection() ?>
