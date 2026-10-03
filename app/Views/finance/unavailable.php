<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['context' => 'Operaciones', 'title' => $title, 'description' => $description], ['saveData' => false]) ?>
<?php if (!empty($client)): ?>
<?= view('partials/client_navigation', ['client' => $client], ['saveData' => false]) ?>
<?php endif ?>
<a class="btn" href="<?= site_url('clientes') ?>">Volver a clientes</a>
<?= $this->endSection() ?>
