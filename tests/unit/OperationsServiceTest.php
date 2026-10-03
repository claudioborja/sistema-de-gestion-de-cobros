<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OperationsService;
use CodeIgniter\Test\CIUnitTestCase;

final class OperationsServiceTest extends CIUnitTestCase
{
    public function testStatusIsolatesProbeFailuresAndCountsEveryLevel(): void
    {
        $service = new OperationsService([
            'application' => [
                'component' => 'Aplicación web',
                'probe' => static fn (): array => ['level' => 'success', 'state' => 'Operativo', 'detail' => 'Respuesta verificada.'],
            ],
            'database' => [
                'component' => 'Base de datos',
                'probe' => static function (): array {
                    throw new \RuntimeException('connection details must stay private');
                },
            ],
            'email' => [
                'component' => 'Correo saliente',
                'probe' => static fn (): array => ['level' => 'unknown', 'state' => 'No configurado', 'detail' => 'Sin configuración.'],
            ],
            'backups' => [
                'component' => 'Respaldos',
                'probe' => static fn (): array => ['level' => 'warning', 'state' => 'Atención', 'detail' => 'Último respaldo antiguo.'],
            ],
        ], static fn (): \DateTimeImmutable => new \DateTimeImmutable('2026-09-14 10:45:00', new \DateTimeZone('America/Guayaquil')));

        $result = $service->status();

        $this->assertSame(['success' => 1, 'warning' => 1, 'error' => 1, 'unknown' => 1], $result['summary']);
        $this->assertSame('14/09/2026 10:45:00', $result['checkedAt']);
        $this->assertSame('error', $result['checks'][1]['level']);
        $this->assertSame('No disponible', $result['checks'][1]['state']);
        $this->assertStringNotContainsString('connection details', $result['checks'][1]['detail']);
    }
}
