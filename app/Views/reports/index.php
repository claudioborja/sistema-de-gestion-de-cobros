<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>

<?= view('partials/page_header', ['context' => 'Reportes y operación', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>

<section class="stats stats-vertical w-full border border-base-300 bg-base-100 lg:stats-horizontal" aria-label="Resumen general">
    <div class="stat ledger-stat"><div class="stat-title">Clientes</div><div class="stat-value font-data text-3xl" data-report="clients"><?= $clients ?></div><div class="stat-desc mt-2"><span class="font-data"><?= $clientsActive ?></span> activos</div></div>
    <div class="stat"><div class="stat-title">Catálogo</div><div class="stat-value font-data text-3xl"><?= $items ?></div><div class="stat-desc mt-2"><span class="font-data"><?= $itemsActive ?></span> ítems activos</div></div>
    <div class="stat"><div class="stat-title">Actividad de hoy</div><div class="stat-value font-data text-3xl"><?= $eventsToday ?></div><div class="stat-desc mt-2">Día local · America/Guayaquil</div></div>
</section>

<div class="mt-8 grid gap-6 md:grid-cols-2">
    <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Directorio de clientes</h2><p>Consulta el universo registrado y aplica filtros por nombre, identificación o estado.</p><div class="card-actions mt-3"><a class="btn" href="<?= site_url('clientes') ?>">Abrir directorio<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></a></div></div></section>
    <section class="card card-border bg-base-100"><div class="card-body"><h2 class="card-title">Trazabilidad</h2><p>Revisa quién realizó cada alta, modificación o cambio de estado.</p><div class="card-actions mt-3"><a class="btn" href="<?= site_url('operacion/auditoria') ?>">Ver auditoría<i data-lucide="arrow-right" class="icon" aria-hidden="true"></i></a></div></div></section>
</div>

<section class="mt-8" aria-labelledby="reportes-operativos-title">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><p class="eyebrow mb-2">Lecturas financieras</p><h2 id="reportes-operativos-title" class="text-2xl font-semibold">Reportes financieros</h2><p class="mt-1 text-sm text-base-content/70">Consulta y exporta el conjunto completo de cartera y cobros aplicando los mismos filtros.</p></div><a class="btn btn-soft" href="<?= site_url('operacion/estado') ?>"><i data-lucide="activity" class="icon" aria-hidden="true"></i>Comprobaciones básicas</a></div>
    <div class="grid gap-4 md:grid-cols-2">
        <a class="card card-border bg-base-100 transition hover:-translate-y-0.5 hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href="<?= site_url('reportes/cartera') ?>"><div class="card-body"><div class="flex items-center justify-between"><i data-lucide="wallet" class="icon text-primary" aria-hidden="true"></i><i data-lucide="arrow-up-right" class="icon" aria-hidden="true"></i></div><h3 class="card-title mt-3">Cartera</h3><p class="text-sm text-base-content/70">Saldos, vencimientos y obligaciones que requieren gestión.</p></div></a>
        <a class="card card-border bg-base-100 transition hover:-translate-y-0.5 hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" href="<?= site_url('reportes/pagos') ?>"><div class="card-body"><div class="flex items-center justify-between"><i data-lucide="receipt" class="icon text-primary" aria-hidden="true"></i><i data-lucide="arrow-up-right" class="icon" aria-hidden="true"></i></div><h3 class="card-title mt-3">Pagos</h3><p class="text-sm text-base-content/70">Pagos confirmados y trazabilidad por fecha.</p></div></a>
    </div>
</section>

<?= $this->endSection() ?>
