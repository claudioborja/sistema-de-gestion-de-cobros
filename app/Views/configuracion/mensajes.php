<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $key, string $default = '') => old($key, $messages[$key] ?? $default);
?>

<?= view('partials/page_header', [
    'context' => 'Comunicación',
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
    <form method="post" action="<?= site_url('configuracion/mensajes') ?>" class="card card-border bg-base-100 form-card">
        <div class="card-body">
            <?= csrf_field() ?>

            <input type="hidden" name="default_message_method" value="email">
            <div class="alert alert-info alert-soft"><i data-lucide="info" class="icon" aria-hidden="true"></i><span>El correo es el único canal de envío habilitado en esta etapa.</span></div>

            <label class="fieldset" for="invoice_subject">
                <span class="fieldset-legend text-lg">Asunto del correo de comprobante</span>
                <input id="invoice_subject" name="invoice_subject" class="input w-full <?= isset($errors['invoice_subject']) ? 'input-error' : '' ?>" type="text" value="<?= esc($value('invoice_subject'), 'attr') ?>" maxlength="140" required autocomplete="off">
                <?php if (isset($errors['invoice_subject'])): ?><span id="invoice_subject-error" class="field-error"><?= esc($errors['invoice_subject']) ?></span><?php endif ?>
            </label>

            <label class="fieldset" for="invoice_body">
                <span class="fieldset-legend">Plantilla de comprobante</span>
                <textarea id="invoice_body" name="invoice_body" class="textarea h-32 w-full <?= isset($errors['invoice_body']) ? 'textarea-error' : '' ?>" maxlength="1200" required><?= esc($value('invoice_body'), 'attr') ?></textarea>
                <?php if (isset($errors['invoice_body'])): ?><span id="invoice_body-error" class="field-error"><?= esc($errors['invoice_body']) ?></span><?php endif ?>
            </label>
            <p class="font-data text-xs text-base-content/70">Variables disponibles: <code>{{empresa}}</code>, <code>{{cliente}}</code>, <code>{{monto}}</code>, <code>{{fecha}}</code>, <code>{{fecha_vencimiento}}</code>, <code>{{comprobante}}</code>.</p>

            <label class="fieldset" for="reminder_body">
                <span class="fieldset-legend">Plantilla de recordatorio de pago</span>
                <textarea id="reminder_body" name="reminder_body" class="textarea h-32 w-full <?= isset($errors['reminder_body']) ? 'textarea-error' : '' ?>" maxlength="1200" required><?= esc($value('reminder_body'), 'attr') ?></textarea>
                <?php if (isset($errors['reminder_body'])): ?><span id="reminder_body-error" class="field-error"><?= esc($errors['reminder_body']) ?></span><?php endif ?>
            </label>

            <label class="fieldset flex items-start gap-3">
                <input class="checkbox mt-1" id="invoice_send_copy" name="invoice_send_copy" type="checkbox" value="1" <?= old('invoice_send_copy', !empty($messages['invoice_send_copy']) ? '1' : '') === '1' ? 'checked' : '' ?>>
                <span>Enviar copia del comprobante al correo alterno del negocio.</span>
            </label>
            <label class="fieldset flex items-start gap-3">
                <input class="checkbox mt-1" id="reminder_copy_self" name="reminder_copy_self" type="checkbox" value="1" <?= old('reminder_copy_self', !empty($messages['reminder_copy_self']) ? '1' : '') === '1' ? 'checked' : '' ?>>
                <span>Enviar recordatorios a usuario administrador.</span>
            </label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Guardar plantillas</button>
            </div>
        </div>
    </form>

    <aside class="card card-border bg-base-100 form-summary">
        <div class="card-body">
            <h2 class="card-title">Vista previa con datos de muestra</h2>
            <div class="space-y-4">
                <div><p class="font-semibold">Comprobante</p><p class="mt-1 text-sm text-base-content/80"><?= esc($messagePreview['invoice']) ?></p></div>
                <div><p class="font-semibold">Recordatorio</p><p class="mt-1 text-sm text-base-content/80"><?= esc($messagePreview['reminder']) ?></p></div>
            </div>
        </div>
    </aside>
</div>

<?= $this->endSection() ?>
