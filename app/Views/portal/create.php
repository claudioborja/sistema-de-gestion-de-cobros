<?= $this->extend('layouts/portal') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static function (string $key, string $default = ''): string {
    $input = old($key, $default, false);
    return is_scalar($input) ? (string) $input : '';
};
?>

<?= view('partials/page_header', ['title' => $title, 'description' => $description, 'breadcrumbs' => [['label' => 'Mis comprobantes', 'url' => site_url('portal/reportes-pago')], ['label' => 'Registrar']]], ['saveData' => false]) ?>

<?php if ($errors): ?><div class="alert alert-error mb-6" role="alert" tabindex="-1"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><div><h2 class="font-semibold">No se pudo registrar el comprobante</h2><ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $key => $error): ?><li><a class="underline" href="#<?= esc((string) $key, 'attr') ?>"><?= esc($error) ?></a></li><?php endforeach ?></ul></div></div><?php endif ?>

<section class="grid items-start gap-6 lg:grid-cols-12">
    <form method="post" enctype="multipart/form-data" action="<?= site_url('portal/reportes-pago') ?>" class="card card-border bg-base-100 lg:col-span-8">
        <div class="card-body">
            <?= csrf_field() ?><input type="hidden" name="idempotency_key" value="<?= esc($idempotencyKey, 'attr') ?>">
            <?php if (!$bankAccounts): ?>
                <div class="alert alert-warning mb-6" role="status"><i data-lucide="landmark" class="icon" aria-hidden="true"></i><div><h2 class="font-semibold">No hay cuentas receptoras disponibles</h2><p class="text-sm">Comunícate con el negocio antes de registrar el comprobante.</p></div></div>
            <?php endif ?>
            <fieldset class="fieldset"><legend class="fieldset-legend text-lg">Datos de la operación bancaria</legend><div class="form-grid">
                <label class="form-field" for="monto"><span>Monto reportado *</span><input class="input w-full font-data <?= isset($errors['monto']) ? 'input-error' : '' ?>" id="monto" name="monto" type="number" min="0.01" step="0.01" inputmode="decimal" value="<?= esc($value('monto'), 'attr') ?>" required><?php if (isset($errors['monto'])): ?><span class="field-error"><?= esc($errors['monto']) ?></span><?php endif ?></label>
                <label class="form-field" for="fecha"><span>Fecha bancaria *</span><input class="input w-full font-data <?= isset($errors['fecha']) ? 'input-error' : '' ?>" id="fecha" name="fecha" type="date" value="<?= esc($value('fecha', date('Y-m-d')), 'attr') ?>" required><?php if (isset($errors['fecha'])): ?><span class="field-error"><?= esc($errors['fecha']) ?></span><?php endif ?></label>
                <label class="form-field" for="metodo"><span>Método *</span><select class="select w-full <?= isset($errors['metodo']) ? 'select-error' : '' ?>" id="metodo" name="metodo" required><option value="TRANSFERENCIA" <?= $value('metodo', 'TRANSFERENCIA') === 'TRANSFERENCIA' ? 'selected' : '' ?>>Transferencia</option><option value="DEPOSITO" <?= $value('metodo') === 'DEPOSITO' ? 'selected' : '' ?>>Depósito</option></select><?php if (isset($errors['metodo'])): ?><span class="field-error"><?= esc($errors['metodo']) ?></span><?php endif ?></label>
                <label class="form-field" for="cuenta_bancaria_id"><span>Cuenta receptora *</span><select class="select w-full <?= isset($errors['cuenta_bancaria_id']) ? 'select-error' : '' ?>" id="cuenta_bancaria_id" name="cuenta_bancaria_id" required><option value="">Selecciona la cuenta</option><?php foreach ($bankAccounts as $account): ?><option value="<?= (int) $account['id'] ?>" <?= $value('cuenta_bancaria_id') === (string) $account['id'] ? 'selected' : '' ?>><?= esc($account['institucion'] . ' · ' . $account['alias'] . ' · ' . $account['numero_cuenta']) ?></option><?php endforeach ?></select><?php if (isset($errors['cuenta_bancaria_id'])): ?><span class="field-error"><?= esc($errors['cuenta_bancaria_id']) ?></span><?php endif ?></label>
                <label class="form-field sm:col-span-2" for="referencia"><span>Referencia bancaria *</span><input class="input w-full font-data <?= isset($errors['referencia']) ? 'input-error' : '' ?>" id="referencia" name="referencia" maxlength="120" value="<?= esc($value('referencia'), 'attr') ?>" required autocomplete="off"><span class="text-sm text-base-content/70">Copia el código exactamente como aparece en el banco.</span><?php if (isset($errors['referencia'])): ?><span class="field-error"><?= esc($errors['referencia']) ?></span><?php endif ?></label>
            </div></fieldset>
            <fieldset class="fieldset mt-6"><legend class="fieldset-legend text-lg">Evidencia</legend><label class="form-field" for="attachment"><span>Archivo del comprobante *</span><input class="file-input w-full <?= isset($errors['attachment']) ? 'file-input-error' : '' ?>" id="attachment" name="attachment" type="file" accept="application/pdf,image/png,image/jpeg" required><span class="text-sm text-base-content/70">PDF, PNG o JPEG de hasta 2 MB.</span><?php if (isset($errors['attachment'])): ?><span class="field-error"><?= esc($errors['attachment']) ?></span><?php endif ?></label><label class="form-field mt-4" for="observacion"><span>Observación</span><textarea class="textarea min-h-24 w-full" id="observacion" name="observacion" maxlength="500"><?= esc($value('observacion')) ?></textarea></label></fieldset>
            <div class="alert alert-warning alert-soft mt-6" role="status"><i data-lucide="info" class="icon" aria-hidden="true"></i><span>Registrar el comprobante no confirma el ingreso ni reduce una deuda. El negocio debe verificarlo.</span></div>
            <div class="form-actions"><a class="btn" href="<?= site_url('portal/reportes-pago') ?>">Cancelar</a><button class="btn btn-primary" type="submit" <?= !$bankAccounts ? 'disabled' : '' ?>><i data-lucide="upload" class="icon" aria-hidden="true"></i>Registrar comprobante</button></div>
        </div>
    </form>
    <aside class="card card-border bg-base-100 lg:col-span-4"><div class="card-body"><p class="eyebrow">Cuenta vinculada</p><h2 class="card-title mt-1"><?= esc($client['nombre']) ?></h2><ul class="mt-4 space-y-3 text-sm text-base-content/70"><li class="flex gap-2"><i data-lucide="circle-check" class="icon shrink-0 text-success" aria-hidden="true"></i><span>Verifica monto, fecha y referencia antes de enviar.</span></li><li class="flex gap-2"><i data-lucide="circle-check" class="icon shrink-0 text-success" aria-hidden="true"></i><span>La evidencia queda almacenada de forma privada.</span></li><li class="flex gap-2"><i data-lucide="circle-check" class="icon shrink-0 text-success" aria-hidden="true"></i><span>Podrás consultar la decisión desde Mis comprobantes.</span></li></ul></div></aside>
</section>

<?= $this->endSection() ?>
