<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$base = 'clientes/' . (int) $client['id'] . '/pagos';
$fieldError = static function (array $errors, string $key): string {
    return isset($errors[$key]) ? '<p id="error-' . esc($key, 'attr') . '" class="label text-error">' . esc($errors[$key]) . '</p>' : '';
};
?>
<?= view('partials/page_header', [
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => [
        ['label' => 'Clientes', 'url' => site_url('clientes')],
        ['label' => $client['nombre'], 'url' => site_url('clientes/' . (int) $client['id'] . '/expediente')],
        ['label' => $title],
    ],
    'primaryAction' => !$creating && $client['activo'] ? ['label' => 'Registrar cobro', 'url' => site_url($base . '/nuevo'), 'icon' => 'plus'] : null,
], ['saveData' => false]) ?>
<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>

<?php if ($creating): ?>
<?php if ($errors): ?><div class="alert alert-error mb-5" role="alert" tabindex="-1" aria-labelledby="payment-errors-title"><i data-lucide="circle-alert" class="icon" aria-hidden="true"></i><div><h2 id="payment-errors-title" class="font-semibold">Revisa el cobro</h2><p><?= esc(implode(' ', array_unique(array_values($errors)))) ?></p></div></div><?php endif ?>
<?php if (!$bankAccounts): ?><div class="alert alert-warning mb-5" role="alert"><i data-lucide="landmark" class="icon" aria-hidden="true"></i><span>No hay cuentas bancarias activas. Registra una antes de confirmar transferencias.</span></div><?php endif ?>
<?php if (\App\Services\Access::can((int) auth()->id(), 'configuracion.gestionar')): ?>
<p class="mb-5"><a class="link" href="<?= site_url('configuracion/pagos') ?>">Administrar cuentas bancarias</a></p>
<?php elseif (!$bankAccounts): ?><p class="mb-5">Solicita al administrador que registre o reactive una cuenta bancaria.</p><?php endif ?>
<form method="post" action="<?= site_url($base) ?>" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <?= csrf_field() ?>
    <input type="hidden" name="cliente_id" value="<?= (int) $client['id'] ?>">
    <input type="hidden" name="idempotency_key" value="<?= esc((string) old('idempotency_key', $idempotencyKey), 'attr') ?>">
    <div class="grid gap-5">
        <section class="card card-border bg-base-100"><div class="card-body grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2"><h2 class="card-title">Transferencia recibida</h2><p class="mt-1 text-sm text-base-content/70">Confirma los datos bancarios antes de distribuir el cobro.</p></div>
            <fieldset class="fieldset"><legend class="fieldset-legend">Importe recibido USD</legend><input id="payment-amount" class="input w-full <?= isset($errors['amount']) ? 'input-error' : '' ?>" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" value="<?= esc((string) old('amount'), 'attr') ?>" required <?= isset($errors['amount']) ? 'aria-describedby="error-amount"' : '' ?>><?= $fieldError($errors, 'amount') ?></fieldset>
            <fieldset class="fieldset"><legend class="fieldset-legend">Fecha bancaria</legend><input id="payment-bank-date" class="input w-full <?= isset($errors['bank_date']) ? 'input-error' : '' ?>" name="bank_date" type="date" value="<?= esc((string) old('bank_date', date('Y-m-d')), 'attr') ?>" required <?= isset($errors['bank_date']) ? 'aria-describedby="error-bank_date"' : '' ?>><?= $fieldError($errors, 'bank_date') ?></fieldset>
            <fieldset class="fieldset"><legend class="fieldset-legend">Cuenta receptora</legend><select id="payment-account" class="select w-full <?= isset($errors['bank_account_id']) ? 'select-error' : '' ?>" name="bank_account_id" required <?= isset($errors['bank_account_id']) ? 'aria-describedby="error-bank_account_id"' : '' ?>><option value="">Selecciona una cuenta</option><?php foreach ($bankAccounts as $account): ?><option value="<?= (int) $account['id'] ?>" <?= (string) old('bank_account_id') === (string) $account['id'] ? 'selected' : '' ?>><?= esc($account['alias']) ?> · <?= esc($account['institucion']) ?> · <?= esc($account['numero_cuenta']) ?></option><?php endforeach ?></select><?= $fieldError($errors, 'bank_account_id') ?></fieldset>
            <fieldset class="fieldset"><legend class="fieldset-legend">Referencia bancaria</legend><input id="payment-reference" class="input w-full <?= isset($errors['reference']) ? 'input-error' : '' ?>" name="reference" maxlength="120" value="<?= esc((string) old('reference'), 'attr') ?>" autocomplete="off" required <?= isset($errors['reference']) ? 'aria-describedby="error-reference"' : '' ?>><?= $fieldError($errors, 'reference') ?></fieldset>
        </div></section>
        <section class="card card-border bg-base-100"><div class="card-body">
            <div><h2 class="card-title">Distribución en cuotas</h2><p class="mt-1 text-sm text-base-content/70">Indica cuánto aplicar a cada cuota. La suma no puede superar el importe recibido ni el saldo de la cuota.</p></div>
            <?php if (!$installments): ?>
                <div class="alert mt-4" role="status"><i data-lucide="circle-check" class="icon" aria-hidden="true"></i><span>Este cliente no tiene cuotas pendientes. El cobro quedará disponible sin aplicar.</span></div>
            <?php else: ?>
                <div class="mt-4 grid gap-3">
                <?php foreach ($installments as $index => $installment): ?>
                    <fieldset class="fieldset rounded-box border border-base-300 p-4">
                        <legend class="fieldset-legend">Documento #<?= (int) $installment['obligacion_id'] ?> · cuota <?= (int) $installment['numero_cuota'] ?></legend>
                        <input type="hidden" name="applications[<?= $index ?>][installment_id]" value="<?= (int) $installment['id'] ?>">
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem] sm:items-end"><div><p class="font-medium"><?= esc($installment['numero_completo'] ?: $installment['tipo']) ?> · <?= esc($installment['concepto']) ?></p><p class="font-data mt-1 text-sm text-base-content/70">Vence <?= esc($installment['fecha_vencimiento']) ?> · saldo $<?= esc($installment['saldo']) ?></p></div><label class="fieldset"><span class="fieldset-legend">Aplicar USD</span><input class="input w-full" name="applications[<?= $index ?>][amount]" type="number" min="0.01" max="<?= esc($installment['saldo'], 'attr') ?>" step="0.01" inputmode="decimal" value="<?= esc((string) old('applications.' . $index . '.amount'), 'attr') ?>" placeholder="0.00"></label></div>
                    </fieldset>
                <?php endforeach ?>
                </div>
            <?php endif ?>
            <?= $fieldError($errors, 'applications') ?>
        </div></section>
    </div>
    <aside class="grid content-start gap-5 lg:order-last">
        <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Revisión</h2><dl class="mt-2 grid gap-3 text-sm"><div><dt class="text-base-content/70">Cliente</dt><dd class="font-medium"><?= esc($client['nombre']) ?></dd></div><div><dt class="text-base-content/70">Medio</dt><dd>Transferencia bancaria</dd></div><div><dt class="text-base-content/70">Cuotas pendientes</dt><dd class="font-data"><?= count($installments) ?></dd></div></dl><p class="mt-3 text-sm text-base-content/70">La parte no distribuida quedará disponible para una aplicación posterior.</p><div class="card-actions mt-4 flex-col items-stretch"><button class="btn btn-primary" type="submit" <?= !$bankAccounts ? 'disabled' : '' ?> data-confirm="Confirma que la cuenta, referencia, importe y distribución son correctos. El cobro afectará la cartera inmediatamente.">Confirmar cobro</button><a class="btn" href="<?= site_url($base) ?>">Cancelar</a></div></div></section>
    </aside>
</form>
<?php else: ?>
<?php if (!$rows): ?>
<?= view('partials/empty_state', ['icon' => 'receipt', 'title' => 'Aún no hay cobros', 'description' => 'Registra una transferencia para iniciar la trazabilidad.', 'action' => $client['activo'] ? ['label' => 'Registrar cobro', 'url' => site_url($base . '/nuevo')] : null], ['saveData' => false]) ?>
<?php else: ?>
<section class="card card-border bg-base-100"><div class="card-body p-0"><div class="hidden overflow-x-auto md:block"><table class="table"><caption class="sr-only">Cobros del cliente</caption><thead><tr><th>Cobro</th><th>Referencia</th><th>Fecha</th><th>Importe</th><th>Aplicado</th><th>Disponible</th><th>Estado</th><th><span class="sr-only">Acción</span></th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><th class="font-data">#<?= (int) $row['id'] ?></th><td><?= esc($row['referencia'] ?: 'Sin referencia') ?></td><td class="font-data"><?= esc($row['fecha_declarada']) ?></td><td class="font-data">$<?= esc($row['importe']) ?></td><td class="font-data">$<?= esc($row['aplicado']) ?></td><td class="font-data">$<?= esc($row['disponible']) ?></td><td><span class="badge <?= $row['estado'] === 'CONFIRMADO' ? 'badge-success badge-soft' : 'badge-error badge-soft' ?>"><?= esc($row['estado']) ?></span></td><td class="text-right"><a class="btn btn-sm" href="<?= site_url('pagos/' . (int) $row['id']) ?>">Abrir</a></td></tr><?php endforeach ?></tbody></table></div>
<ul class="divide-y divide-base-300 md:hidden" aria-label="Cobros del cliente"><?php foreach ($rows as $row): ?><li class="record-card items-start"><div class="min-w-0 grow"><p class="font-data font-semibold">Cobro #<?= (int) $row['id'] ?></p><p class="mt-1 text-sm"><?= esc($row['referencia'] ?: 'Sin referencia') ?></p><p class="font-data mt-2 text-sm text-base-content/70"><?= esc($row['fecha_declarada']) ?> · $<?= esc($row['importe']) ?></p></div><div class="flex shrink-0 flex-col items-end gap-3"><span class="badge <?= $row['estado'] === 'CONFIRMADO' ? 'badge-success badge-soft' : 'badge-error badge-soft' ?>"><?= esc($row['estado']) ?></span><a class="btn btn-sm" href="<?= site_url('pagos/' . (int) $row['id']) ?>">Abrir</a></div></li><?php endforeach ?></ul></div></section>
<?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
