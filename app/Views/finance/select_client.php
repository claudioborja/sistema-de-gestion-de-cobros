<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['context' => 'Clientes', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>
<?= view('partials/workflow_steps', [
    'id' => 'client-selection-workflow',
    'title' => $action ? 'Iniciar una operación' : 'Abrir información financiera',
    'description' => 'Primero identifica al cliente; el sistema conservará ese contexto en las pantallas siguientes.',
    'steps' => ['Seleccionar cliente', 'Abrir expediente', $section === 'pagos' ? 'Cobros del cliente' : ($section === 'documentos' ? 'Documentos del cliente' : 'Cartera del cliente')],
    'current' => 1,
    'next' => 'Busca por nombre o identificación y confirma el cliente correcto.',
], ['saveData' => false]) ?>
<section class="card card-border max-w-3xl bg-base-100">
    <div class="card-body">
        <form method="get" class="grid gap-4" action="<?= site_url($section . ($action ? '/' . $action : '')) ?>">
            <label class="form-field" for="workflow-client"><span>Cliente</span>
                <select id="workflow-client" class="select w-full" name="cliente_id" required data-client-select data-source="<?= site_url('clientes/datos') ?>" <?= !empty($activeOnly) ? 'data-active-only="true"' : '' ?>>
                    <option value="">Buscar por nombre o identificación</option>
                </select>
            </label>
            <?php if (!empty($activeOnly)): ?><p class="text-sm text-base-content/70">Solo se muestran clientes activos porque vas a iniciar una operación.</p><?php endif ?>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Continuar con el cliente</button>
                <a class="btn" href="<?= site_url('clientes') ?>">Abrir directorio de clientes</a>
            </div>
            <noscript><p>Abre el directorio y entra al expediente del cliente para continuar.</p></noscript>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
