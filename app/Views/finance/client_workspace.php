<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $base = 'clientes/' . (int) $client['id'] . '/' . $section; ?>
<?= view('partials/page_header', [
    'context' => 'Expediente del cliente', 'title' => $title, 'description' => $description, 'icon' => $icon,
    'breadcrumbs' => [['label' => 'Clientes', 'url' => site_url('clientes')], ['label' => $client['nombre'], 'url' => site_url('clientes/' . (int) $client['id'] . '/expediente')], ['label' => $title]],
    'primaryAction' => !$creating && $canCreate && $client['activo'] && $section !== 'cartera' ? ['label' => $section === 'pagos' ? 'Registrar cobro' : 'Nuevo documento', 'url' => site_url($base . '/nuevo'), 'icon' => 'plus'] : null,
], ['saveData' => false]) ?>
<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>
<?= view('partials/workflow_steps', [
    'id' => 'client-finance-workflow',
    'title' => $section === 'pagos' ? 'Cobro del cliente' : ($section === 'documentos' ? 'Documento del cliente' : 'Cartera del cliente'),
    'description' => 'El expediente mantiene visible al cliente durante toda la operación.',
    'steps' => ['Cliente seleccionado', 'Expediente revisado', $section === 'pagos' ? 'Registrar cobro' : ($section === 'documentos' ? 'Registrar documento' : 'Revisar cartera'), 'Resultado'],
    'current' => 3,
    'next' => $creating ? 'La confirmación se habilitará cuando existan obligaciones y saldos verificables.' : 'Revisa el historial o inicia una operación disponible.',
], ['saveData' => false]) ?>
<div class="alert mb-6" role="status"><i data-lucide="info" class="icon" aria-hidden="true"></i><p>El registro financiero aún no está habilitado. No hay saldos ni operaciones verificadas disponibles para este cliente.</p></div>
<?php if (!$client['activo']): ?><div class="alert alert-warning mb-6" role="status">Cliente inactivo. No se permiten nuevas operaciones.</div><?php endif ?>
<?php if ($creating): ?>
<section class="card card-border bg-base-100"><div class="card-body">
    <h2 class="card-title"><?= $section === 'pagos' ? 'Datos del cobro' : 'Datos del documento' ?></h2>
    <form class="grid gap-4" method="post" action="<?= site_url($base) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="cliente_id" value="<?= (int) $client['id'] ?>">
        <label class="form-field"><span>Cliente</span><input class="input w-full" value="<?= esc($client['nombre'], 'attr') ?>" readonly></label>
        <?php if ($section === 'pagos'): ?>
            <label class="form-field"><span>Obligación del cliente</span><select class="select w-full" disabled><option>No hay obligaciones verificadas disponibles</option></select></label>
            <label class="form-field"><span>Medio de pago</span><select class="select w-full" name="medio"><option value="EFECTIVO">Efectivo</option><option value="TRANSFERENCIA">Transferencia</option></select></label>
        <?php else: ?>
            <label class="form-field"><span>Concepto</span><textarea class="textarea w-full" name="concepto" maxlength="1000" required></textarea></label>
        <?php endif ?>
        <label class="form-field"><span>Importe USD</span><input class="input w-full" name="monto" type="number" step="0.01" min="0.01" required autocomplete="off" inputmode="decimal"></label>
        <p id="finance-unavailable" class="text-sm text-base-content/70">La confirmación se habilitará cuando esté disponible el registro financiero y se puedan verificar las obligaciones y saldos.</p>
        <div class="flex flex-wrap justify-end gap-3"><a class="btn" href="<?= site_url($base) ?>">Volver a <?= $section === 'pagos' ? 'cobros' : 'documentos' ?></a><button class="btn btn-primary" type="submit" disabled aria-describedby="finance-unavailable"><?= $section === 'pagos' ? 'Confirmar cobro' : 'Guardar borrador' ?></button></div>
    </form>
</div></section>
<?php else: ?>
<?= view('partials/empty_state', ['icon' => $icon, 'title' => 'Sin registros financieros disponibles', 'description' => 'Las operaciones vinculadas a este cliente aparecerán aquí cuando se habilite su registro.'], ['saveData' => false]) ?>
<?php endif ?>
<?= $this->endSection() ?>
