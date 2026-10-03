<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $key, string $default = '') => old($key, $business[$key] ?? $default);
?>

<?= view('partials/page_header', [
    'context' => 'Configuración',
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => $breadcrumbs,
], ['saveData' => false]) ?>

<?php if ($errors): ?>
    <div class="alert alert-error mb-6" role="alert" tabindex="-1">
        <i data-lucide="circle-alert" class="icon" aria-hidden="true"></i>
        <div>
            <h2 class="font-semibold">No se pudo guardar</h2>
            <ul class="mt-1 list-disc pl-5">
                <?php foreach ($errors as $key => $error): ?>
                    <li><a class="underline" href="#<?= esc((string) $key, 'attr') ?>"><?= esc($error) ?></a></li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>

<div class="form-layout">
    <form method="post" action="<?= site_url('configuracion/negocio') ?>" class="card card-border bg-base-100 form-card">
        <div class="card-body">
            <?= csrf_field() ?>

            <fieldset class="fieldset">
                <legend class="fieldset-legend text-lg">Información legal y fiscal</legend>
                <div class="form-grid">
                    <label class="form-field" for="business_name">
                        <span>Nombre del negocio *</span>
                        <input id="business_name" name="business_name" class="input w-full <?= isset($errors['business_name']) ? 'input-error' : '' ?>" type="text" value="<?= esc($value('business_name'), 'attr') ?>" required maxlength="120" autocomplete="organization">
                        <?php if (isset($errors['business_name'])): ?><span id="business_name-error" class="field-error"><?= esc($errors['business_name']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field" for="document_type">
                        <span>Tipo de documento</span>
                        <select id="document_type" name="document_type" class="select w-full <?= isset($errors['document_type']) ? 'select-error' : '' ?>">
                            <?php foreach (['RUC', 'NIT', 'CC', 'No aplica'] as $type): ?>
                                <option value="<?= esc($type, 'attr') ?>" <?= $value('document_type', 'RUC') === $type ? 'selected' : '' ?>><?= esc($type) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?php if (isset($errors['document_type'])): ?><span id="document_type-error" class="field-error"><?= esc($errors['document_type']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field" for="document_value">
                        <span>Número de documento</span>
                        <input id="document_value" name="document_value" class="input w-full <?= isset($errors['document_value']) ? 'input-error' : '' ?>" type="text" value="<?= esc($value('document_value'), 'attr') ?>" maxlength="40" autocomplete="off">
                        <?php if (isset($errors['document_value'])): ?><span id="document_value-error" class="field-error"><?= esc($errors['document_value']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field" for="currency">
                        <span>Moneda de la instalación</span>
                        <input id="currency" name="currency" class="input w-full <?= isset($errors['currency']) ? 'input-error' : '' ?>" value="USD" readonly>
                        <?php if (isset($errors['currency'])): ?><span id="currency-error" class="field-error"><?= esc($errors['currency']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field" for="timezone">
                        <span>Zona horaria</span>
                        <select id="timezone" name="timezone" class="select w-full <?= isset($errors['timezone']) ? 'select-error' : '' ?>" data-enhanced-select>
                            <?php foreach ($timezones as $zone): ?>
                                <option value="<?= esc($zone, 'attr') ?>" <?= $value('timezone', 'America/Guayaquil') === $zone ? 'selected' : '' ?>><?= esc($zone) ?></option>
                            <?php endforeach ?>
                        </select>
                        <?php if (isset($errors['timezone'])): ?><span id="timezone-error" class="field-error"><?= esc($errors['timezone']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field" for="invoice_prefix">
                        <span>Prefijo de comprobante</span>
                        <input id="invoice_prefix" name="invoice_prefix" class="input w-full <?= isset($errors['invoice_prefix']) ? 'input-error' : '' ?>" type="text" value="<?= esc($value('invoice_prefix'), 'attr') ?>" required maxlength="15" autocomplete="off">
                        <?php if (isset($errors['invoice_prefix'])): ?><span id="invoice_prefix-error" class="field-error"><?= esc($errors['invoice_prefix']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field" for="invoice_seed">
                        <span>Número inicial de comprobante</span>
                        <input id="invoice_seed" name="invoice_seed" class="input w-full <?= isset($errors['invoice_seed']) ? 'input-error' : '' ?>" type="number" min="1" max="999999" value="<?= esc($value('invoice_seed', '1'), 'attr') ?>" required autocomplete="off" inputmode="numeric">
                        <?php if (isset($errors['invoice_seed'])): ?><span id="invoice_seed-error" class="field-error"><?= esc($errors['invoice_seed']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field" for="grace_days">
                        <span>Días de gracia</span>
                        <input id="grace_days" name="grace_days" class="input w-full <?= isset($errors['grace_days']) ? 'input-error' : '' ?>" type="number" min="0" max="365" value="<?= esc($value('grace_days', '0'), 'attr') ?>" autocomplete="off" inputmode="numeric">
                        <?php if (isset($errors['grace_days'])): ?><span id="grace_days-error" class="field-error"><?= esc($errors['grace_days']) ?></span><?php endif ?>
                    </label>

                    <label class="form-field flex items-start gap-3 pt-1">
                        <input class="checkbox mt-1" id="require_signature" name="require_signature" type="checkbox" value="1" <?= old('require_signature', !empty($business['require_signature']) ? '1' : '') === '1' ? 'checked' : '' ?>>
                        <span>Requerir firma digital o física en comprobantes internos.</span>
                    </label>

                    <label class="form-field flex items-start gap-3 pt-1">
                        <input class="checkbox mt-1" id="portal_enabled" name="portal_enabled" type="checkbox" value="1" <?= old('portal_enabled', !empty($business['portal_enabled']) ? '1' : '') === '1' ? 'checked' : '' ?>>
                        <span><strong>Habilitar portal del cliente.</strong><small class="mt-1 block text-base-content/70">Confirma que el negocio revisó el aviso de privacidad y el procedimiento de invitaciones.</small></span>
                    </label>
                </div>
            </fieldset>

            <label class="fieldset" for="invoice_footer">
                <span class="fieldset-legend">Texto de pie del comprobante</span>
                <textarea id="invoice_footer" name="invoice_footer" class="textarea h-24 w-full <?= isset($errors['invoice_footer']) ? 'textarea-error' : '' ?>" maxlength="240"><?= esc($value('invoice_footer'), 'attr') ?></textarea>
                <?php if (isset($errors['invoice_footer'])): ?><span id="invoice_footer-error" class="field-error"><?= esc($errors['invoice_footer']) ?></span><?php endif ?>
            </label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Guardar configuración</button>
            </div>
        </div>
    </form>

    <aside class="card card-border bg-base-100 form-summary">
        <div class="card-body">
            <h2 class="card-title">Resumen</h2>
            <p>Los datos se guardan para el negocio y permanecen al cerrar sesión. Los documentos y las cuotas existentes conservan sus valores.</p>
            <p class="mt-3 font-data text-sm text-base-content/70">Última actualización: <?= esc($business['updated_at'] ? date('d/m/Y H:i', strtotime($business['updated_at'])) : 'Sin cambios') ?></p>
        </div>
    </aside>
</div>

<?= $this->endSection() ?>
