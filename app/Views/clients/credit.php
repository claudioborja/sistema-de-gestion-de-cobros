<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', [
    'context' => 'Finanzas del cliente',
    'title' => $title,
    'description' => $description,
    'breadcrumbs' => $breadcrumbs,
    'primaryAction' => ['label' => 'Volver', 'url' => site_url('clientes/' . $client['id'] . '/expediente'), 'icon' => 'arrow-left'],
], ['saveData' => false]) ?>

<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>
<section class="card card-border bg-base-100">
    <div class="card-body">
        <h2 class="card-title">Venta a crédito por meses</h2>
        <p>Define el plazo de cada venta de 1 a 120 meses, con una cuota mensual y sin intereses.</p>
        <ol class="my-4 list-decimal space-y-2 pl-5">
            <li>Registra los ítems y guarda el documento como borrador.</li>
            <li>Elige el número de meses y la fecha de la primera cuota.</li>
            <li>Revisa los vencimientos y confirma la venta para generar la deuda.</li>
            <li>Registra cada cobro y aplícalo a las cuotas correspondientes.</li>
        </ol>
        <?php if (\App\Services\Access::can((int) auth()->id(), 'cartera.ver')): ?>
        <div class="card-actions flex-wrap">
            <?php if ($client['activo']): ?><a class="btn btn-primary" href="<?= site_url('clientes/' . (int) $client['id'] . '/documentos/nuevo') ?>">Nueva venta a crédito</a><?php else: ?><p>Activa el cliente para registrar una venta nueva.</p><?php endif ?>
            <a class="btn" href="<?= site_url('clientes/' . (int) $client['id'] . '/documentos') ?>">Ver documentos y borradores</a>
            <a class="btn" href="<?= site_url('clientes/' . (int) $client['id'] . '/cartera') ?>">Ver cuotas y saldos</a>
        </div>
        <?php endif ?>
    </div>
</section>

<?= $this->endSection() ?>
