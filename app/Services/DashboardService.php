<?php

declare(strict_types=1);

namespace App\Services;

final class DashboardService
{
    /**
     * Prepara un resumen operativo usando únicamente módulos ya disponibles.
     *
     * @return array<string, mixed>
     */
    public function overview(int $actor): array
    {
        $db = db_connect();
        $canManageCatalog = Access::can($actor, 'catalogo.gestionar');
        $canViewAudit = Access::can($actor, 'auditoria.ver');
        $clientsTotal = $db->table('clientes')->countAllResults();
        $clientsActive = $db->table('clientes')->where('activo', 1)->countAllResults();
        $catalogTotal = $canManageCatalog ? $db->table('items')->countAllResults() : 0;
        $catalogActive = $canManageCatalog ? $db->table('items')->where('activo', 1)->countAllResults() : 0;

        $recentClients = $db->table('clientes')
            ->select('id, nombre, activo, creado_en')
            ->orderBy('id', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $recentActivity = [];
        if ($canViewAudit) {
            $events = $db->table('auditoria_eventos')
                ->select('accion, referencia_textual, registrado_en')
                ->where('usuario_id', $actor)
                ->orderBy('registrado_en', 'DESC')
                ->orderBy('id', 'DESC')
                ->limit(6)
                ->get()
                ->getResultArray();

            foreach ($events as $event) {
                $recentActivity[] = [
                    'label' => $this->activityLabel((string) $event['accion']),
                    'reference' => $this->referenceLabel((string) $event['referencia_textual']),
                    'occurredAt' => $this->localDateTime((string) $event['registrado_en']),
                ];
            }
        }

        $readinessChecks = [
            ['label' => 'Clientes activos', 'ready' => $clientsActive > 0, 'url' => 'clientes'],
        ];
        if ($canManageCatalog) {
            $readinessChecks[] = ['label' => 'Ítems activos del catálogo', 'ready' => $catalogActive > 0, 'url' => 'catalogo'];
        }
        $readyCount = count(array_filter($readinessChecks, static fn (array $check): bool => $check['ready']));
        $readiness = $readinessChecks === [] ? 0 : (int) round(($readyCount / count($readinessChecks)) * 100);

        return [
            'dashboardMode' => $canViewAudit ? 'admin' : 'operations',
            'clientsTotal' => $clientsTotal,
            'clientsActive' => $clientsActive,
            'clientsInactive' => $clientsTotal - $clientsActive,
            'catalogTotal' => $catalogTotal,
            'catalogActive' => $catalogActive,
            'recentClients' => $recentClients,
            'recentActivity' => $recentActivity,
            'readinessChecks' => $readinessChecks,
            'readiness' => $readiness,
            'canCreateClient' => Access::can($actor, 'clientes.crear'),
            'canEditClient' => Access::can($actor, 'clientes.editar'),
            'canManageCatalog' => $canManageCatalog,
            'canViewAudit' => $canViewAudit,
        ];
    }

    private function activityLabel(string $action): string
    {
        return match ($action) {
            'clientes.crear' => 'Registraste un cliente',
            'clientes.editar' => 'Actualizaste un cliente',
            'clientes.estado' => 'Cambiaste el estado de un cliente',
            'catalogo.guardar' => 'Actualizaste el catálogo',
            'usuarios.alta_local' => 'Creaste el acceso administrativo local',
            default => 'Realizaste una acción administrativa',
        };
    }

    private function referenceLabel(string $reference): string
    {
        if (preg_match('/^(cliente|item|usuario):(\d+)$/D', $reference, $matches) !== 1) {
            return 'Registro interno';
        }

        $labels = ['cliente' => 'Cliente', 'item' => 'Ítem', 'usuario' => 'Usuario'];

        return $labels[$matches[1]] . ' #' . $matches[2];
    }

    private function localDateTime(string $value): string
    {
        $utc = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));

        return $utc->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
    }
}
