<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Services\Audit;
use App\Services\SubscriptionService;
use CodeIgniter\Database\Seeder;

final class DemoSubscriptions extends Seeder
{
    private const string DATASET = 'dataset-demo-suscripciones:v1';

    public function run(): void
    {
        if (ENVIRONMENT !== 'development' || $this->db->database !== 'cobros_dev') {
            throw new \RuntimeException('Las suscripciones de demostracion solo se permiten en cobros_dev, entorno development.');
        }
        if ($this->db->table('auditoria_eventos')->where('accion', 'demo.suscripciones.pobladas')->where('referencia_textual', self::DATASET)->countAllResults() > 0) {
            echo "Las suscripciones de demostracion v1 ya existen; no se agregaron duplicados.\n";
            return;
        }

        $admin = $this->db->table('users')->select('id')->where('username', 'admin')->get()->getRowArray();
        if (!$admin) {
            throw new \RuntimeException('Primero ejecuta el seeder LocalAccess para crear el usuario administrador.');
        }
        $actorId = (int) $admin['id'];
        $clients = $this->activeClients();
        if (count($clients) < 4) {
            throw new \RuntimeException('Primero ejecuta DemoData para crear clientes activos de demostracion.');
        }

        $moodle = $this->serviceItem($actorId, 'DEMO-SRV-MOODLE', 'Acceso a Moodle', '25.00');
        $support = $this->findItem('DEMO-SRV-02') ?? $this->serviceItem($actorId, 'DEMO-SRV-SOPORTE', 'Soporte tecnico mensual', '120.00');
        $advisory = $this->findItem('DEMO-SRV-04') ?? $this->serviceItem($actorId, 'DEMO-SRV-ASESORIA', 'Asesoria administrativa', '150.00');

        $subscriptions = new SubscriptionService();
        $created = [];

        $created[] = $subscriptions->create($actorId, $clients[0], [
            'item_id' => (string) $moodle,
            'start' => '2026-06-01',
            'end' => '',
            'months' => '1',
            'policy' => 'ANCLA',
            'amount' => '25.00',
            'billing' => 'ANTICIPADO',
            'days' => '5',
            'acceptance' => '0',
        ], 'demo:v1:suscripcion:moodle:crear');

        $created[] = $subscriptions->create($actorId, $clients[1], [
            'item_id' => (string) $support,
            'start' => '2026-07-15',
            'end' => '',
            'months' => '1',
            'policy' => 'ANCLA',
            'amount' => '120.00',
            'billing' => 'VENCIDO',
            'days' => '0',
            'acceptance' => '0',
        ], 'demo:v1:suscripcion:soporte:crear');

        $created[] = $subscriptions->create($actorId, $clients[2], [
            'item_id' => (string) $advisory,
            'start' => '2026-09-01',
            'end' => '',
            'months' => '12',
            'policy' => 'ANCLA',
            'amount' => '1500.00',
            'billing' => 'ANTICIPADO',
            'days' => '10',
            'acceptance' => '0',
        ], 'demo:v1:suscripcion:anual:crear');

        $created[] = $subscriptions->create($actorId, $clients[3], [
            'item_id' => (string) $moodle,
            'start' => '2026-09-17',
            'end' => '',
            'months' => '1',
            'policy' => 'ANCLA',
            'amount' => '30.00',
            'billing' => 'ANTICIPADO',
            'days' => '3',
            'acceptance' => '1',
        ], 'demo:v1:suscripcion:moodle:aceptacion:crear');

        $generated = 0;
        foreach (array_slice($created, 0, 3) as $row) {
            $generated += $subscriptions->generate($actorId, (int) $row['id'], SubscriptionService::today());
        }

        Audit::record($actorId, 'demo.suscripciones.pobladas', self::DATASET, [
            'contratos' => count($created),
            'cargos_generados' => $generated,
            'servicio_moodle_id' => $moodle,
        ]);

        echo "Suscripciones de demostracion creadas: " . count($created) . " contratos y {$generated} cargos recurrentes.\n";
    }

    /** @return list<int> */
    private function activeClients(): array
    {
        $rows = $this->db->table('clientes')->select('id')->where('activo', 1)->orderBy('id')->limit(8)->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function findItem(string $code): ?int
    {
        $row = $this->db->table('items')->select('id')->where('codigo', $code)->where('tipo', 'SERVICIO')->where('activo', 1)->get()->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    private function serviceItem(int $actorId, string $code, string $name, string $price): int
    {
        $existing = $this->findItem($code);
        if ($existing !== null) {
            return $existing;
        }

        $this->db->table('items')->insert([
            'codigo' => $code,
            'tipo' => 'SERVICIO',
            'nombre' => $name,
            'precio_referencia' => $price,
            'activo' => 1,
            'version' => 1,
        ]);
        $itemId = (int) $this->db->insertID();
        Audit::record($actorId, 'catalogo.guardar', 'item:' . $itemId, ['origen' => self::DATASET]);

        return $itemId;
    }
}
