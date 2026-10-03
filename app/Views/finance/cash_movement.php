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
        <h2 id="form-errors-title" class="font-semibold">No se pudo registrar el movimiento</h2>
        <ul class="mt-1 list-disc pl-5">
        <?php foreach ($errors as $key => $error): ?>
            <li><?php if (in_array($key, ['tipo', 'importe', 'concepto'], true)): ?><a class="underline" href="#<?= esc($key, 'attr') ?>"><?= esc($error) ?></a><?php else: ?><?= esc($error) ?><?php endif ?></li>
        <?php endforeach ?>
        </ul>
    </div>
</div>
<?php endif ?>

<section class="grid gap-6 lg:grid-cols-12">
    <form method="post" action="<?= site_url('turnos/' . (int) $shift['id'] . '/movimientos') ?>" class="card card-border bg-base-100 lg:col-span-8">
        <div class="card-body">
            <?= csrf_field() ?>
            <input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
            <fieldset class="fieldset">
                <legend class="fieldset-legend text-lg">Datos del movimiento</legend>
                <div class="form-grid">
                    <label class="form-field" for="tipo">
                        <span>Tipo *</span>
                        <select class="select w-full <?= isset($errors['tipo']) ? 'select-error' : '' ?>" id="tipo" name="tipo" required aria-describedby="tipo-help<?= isset($errors['tipo']) ? ' tipo-error' : '' ?>"<?= isset($errors['tipo']) ? ' aria-invalid="true"' : '' ?>>
                            <option value="">Selecciona el tipo</option>
                            <option value="APORTE" <?= $value('tipo') === 'APORTE' ? 'selected' : '' ?>>Aporte de efectivo</option>
                            <option value="RETIRO" <?= $value('tipo') === 'RETIRO' ? 'selected' : '' ?>>Retiro de efectivo</option>
                            <option value="GASTO" <?= $value('tipo') === 'GASTO' ? 'selected' : '' ?>>Gasto pagado desde caja</option>
                        </select>
                        <span id="tipo-help" class="text-sm text-base-content/70">El tipo determina si el valor aumenta o reduce el efectivo esperado.</span>
                        <?php if (isset($errors['tipo'])): ?><span class="field-error" id="tipo-error"><?= esc($errors['tipo']) ?></span><?php endif ?>
                    </label>
                    <label class="form-field" for="importe">
                        <span>Importe *</span>
                        <input class="input w-full font-data <?= isset($errors['importe']) ? 'input-error' : '' ?>" id="importe" name="importe" type="number" min="0.01" step="0.01" inputmode="decimal" value="<?= esc($value('importe'), 'attr') ?>" required aria-describedby="importe-help<?= isset($errors['importe']) ? ' importe-error' : '' ?>"<?= isset($errors['importe']) ? ' aria-invalid="true"' : '' ?>>
                        <span id="importe-help" class="text-sm text-base-content/70">Retiros y gastos no pueden superar $<?= esc($shift['efectivo_esperado']) ?>.</span>
                        <?php if (isset($errors['importe'])): ?><span class="field-error" id="importe-error"><?= esc($errors['importe']) ?></span><?php endif ?>
                    </label>
                </div>
                <label class="form-field mt-4" for="concepto">
                    <span>Motivo o concepto *</span>
                    <textarea class="textarea min-h-28 w-full <?= isset($errors['concepto']) ? 'textarea-error' : '' ?>" id="concepto" name="concepto" minlength="5" maxlength="300" required aria-describedby="concepto-help<?= isset($errors['concepto']) ? ' concepto-error' : '' ?>"<?= isset($errors['concepto']) ? ' aria-invalid="true"' : '' ?>><?= esc($value('concepto')) ?></textarea>
                    <span id="concepto-help" class="text-sm text-base-content/70">Describe quién entrega o recibe el efectivo y por qué se registra.</span>
                    <?php if (isset($errors['concepto'])): ?><span class="field-error" id="concepto-error"><?= esc($errors['concepto']) ?></span><?php endif ?>
                </label>
            </fieldset>
            <div class="alert alert-warning alert-soft mt-6" role="status"><i data-lucide="triangle-alert" class="icon" aria-hidden="true"></i><span>El movimiento formará parte del arqueo del turno y no se podrá editar ni eliminar.</span></div>
            <div class="form-actions"><a class="btn" href="<?= site_url('mi-caja') ?>">Cancelar</a><button class="btn btn-primary" type="submit"><i data-lucide="plus" class="icon" aria-hidden="true"></i>Registrar movimiento</button></div>
        </div>
    </form>

    <aside class="card card-border bg-base-100 lg:col-span-4" aria-labelledby="shift-context-title">
        <div class="card-body">
            <p class="eyebrow">Contexto invariable</p>
            <h2 id="shift-context-title" class="card-title mt-1"><?= esc($shift['caja_codigo'] . ' — ' . $shift['caja_nombre']) ?></h2>
            <p class="text-sm text-base-content/70">Turno <span class="font-data">#<?= (int) $shift['id'] ?></span> · abierto el <span class="font-data"><?= esc($shift['abierto_en_local']) ?></span></p>
            <div class="stats stats-vertical mt-5 border border-base-300">
                <div class="stat"><div class="stat-title">Fondo inicial</div><div class="stat-value font-data text-2xl">$<?= esc($shift['fondo_inicial']) ?></div></div>
                <div class="stat"><div class="stat-title">Efectivo esperado ahora</div><div class="stat-value font-data text-2xl">$<?= esc($shift['efectivo_esperado']) ?></div><div class="stat-desc">Antes de este movimiento</div></div>
            </div>
        </div>
    </aside>
</section>

<?= $this->endSection() ?>
