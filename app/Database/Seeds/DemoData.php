<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class DemoData extends Seeder
{
    private const string DATASET = 'dataset-demo:v1';

    /** @var list<string> */
    private array $createdFiles = [];

    public function run(): void
    {
        if (ENVIRONMENT !== 'development' || $this->db->database !== 'cobros_dev') {
            throw new \RuntimeException('Los datos de demostración solo se permiten en cobros_dev, entorno development.');
        }
        if ($this->db->table('auditoria_eventos')->where('accion', 'demo.datos.poblados')->where('referencia_textual', self::DATASET)->countAllResults() > 0) {
            echo "Los datos de demostración v1 ya existen; no se agregaron duplicados.\n";
            return;
        }

        $admin = $this->db->table('users')->select('id')->where('username', 'admin')->get()->getRowArray();
        if (!$admin) {
            throw new \RuntimeException('Primero ejecuta el seeder LocalAccess para crear el usuario administrador.');
        }
        $actorId = (int) $admin['id'];
        $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Guayaquil'));

        $this->db->resetTransStatus()->transException(true)->transBegin();
        try {
            $clients = $this->seedClients($actorId, $today);
            $items = $this->seedCatalog($actorId, $today);
            $accounts = $this->seedBankAccounts();
            $financial = $this->seedPortfolio($actorId, $today, $clients, $items, $accounts);
            $this->audit($actorId, 'demo.datos.poblados', self::DATASET, [
                'clientes' => count($clients),
                'items' => count($items),
                'documentos' => $financial['documents'],
                'pagos' => $financial['payments'],
                'adjuntos' => $financial['attachments'],
            ], $today->format('Y-m-d') . ' 18:00:00.000000');

            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new \RuntimeException('No se pudo confirmar el conjunto de demostración.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            foreach ($this->createdFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            throw $error;
        }

        echo "Datos de demostración creados: 30 clientes, 12 ítems, 18 documentos, 9 pagos y 3 adjuntos.\n";
    }

    /** @return list<int> */
    private function seedClients(int $actorId, \DateTimeImmutable $today): array
    {
        $names = [
            'Comercial Andina S.A.', 'Distribuidora Pacífico Cía. Ltda.', 'María Fernanda López',
            'Ferretería El Constructor', 'Servicios Integrales Quito', 'Carlos Andrés Zambrano',
            'Importadora Costa Azul', 'Panadería La Espiga', 'Clínica Veterinaria San Francisco',
            'Tecnología Sierra Norte', 'Ana Lucía Mendoza', 'Transportes Ruta del Sol',
            'Almacenes Nueva Esperanza', 'Juan Sebastián Paredes', 'Constructora Horizonte',
            'Papelería Central', 'Soluciones Eléctricas del Austro', 'Mercado Verde Ecuador',
            'Consultora Punto Clave', 'Mónica Alexandra Cedeño', 'Restaurante Sabor Manabita',
            'Textiles Mitad del Mundo', 'Agroinsumos Los Ríos', 'Diego Fernando Narváez',
            'Muebles Roble Fino', 'Librería Letras del Sur', 'Comercializadora Galápagos',
            'Natalia Belén Ortiz', 'Seguridad Integral Cóndor', 'Fundación Camino Abierto',
        ];
        $cities = ['Quito', 'Guayaquil', 'Cuenca', 'Manta', 'Ambato', 'Loja', 'Santo Domingo', 'Machala'];
        $ids = [];

        foreach ($names as $index => $name) {
            $created = $today->modify('-' . (70 - ($index * 2)) . ' days')->setTime(14, 0, 0)->format('Y-m-d H:i:s.u');
            $this->db->table('clientes')->insert([
                'nombre' => $name,
                'direccion' => 'Av. Principal ' . ($index + 101) . ', ' . $cities[$index % count($cities)],
                'activo' => in_array($index, [8, 19, 27], true) ? 0 : 1,
                'version' => 1,
                'creado_en' => $created,
            ]);
            $clientId = (int) $this->db->insertID();
            $ids[] = $clientId;
            $business = $index % 3 !== 2;
            $this->db->table('cliente_identificaciones')->insert([
                'pais_emisor' => 'EC',
                'tipo' => $business ? 'RUC' : 'CEDULA',
                'numero_normalizado' => $business ? sprintf('179%09d1', $index + 1) : sprintf('09%08d', $index + 1),
                'cliente_id' => $clientId,
            ]);
            $this->db->table('cliente_contactos')->insert([
                'cliente_id' => $clientId,
                'canal' => 'email',
                'valor' => 'cliente' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '@demo.cobros.test',
                'etiqueta' => 'Principal',
                'habilitado_cobranza' => 1,
            ]);
            if ($index % 4 !== 3) {
                $this->db->table('cliente_contactos')->insert([
                    'cliente_id' => $clientId,
                    'canal' => 'telefono',
                    'valor' => '09' . str_pad((string) (70000000 + $index), 8, '0', STR_PAD_LEFT),
                    'etiqueta' => 'Móvil',
                    'habilitado_cobranza' => 1,
                ]);
            }
            $this->audit($actorId, 'clientes.crear', 'cliente:' . $clientId, ['origen' => 'datos_demo'], $created);
        }

        return $ids;
    }

    /** @return list<int> */
    private function seedCatalog(int $actorId, \DateTimeImmutable $today): array
    {
        $catalog = [
            ['DEMO-SRV-01', 'SERVICIO', 'Mantenimiento preventivo', '85.00'],
            ['DEMO-SRV-02', 'SERVICIO', 'Soporte técnico mensual', '120.00'],
            ['DEMO-SRV-03', 'SERVICIO', 'Instalación y configuración', '65.00'],
            ['DEMO-SRV-04', 'SERVICIO', 'Asesoría administrativa', '150.00'],
            ['DEMO-SRV-05', 'SERVICIO', 'Transporte local', '45.00'],
            ['DEMO-PRD-01', 'PRODUCTO', 'Equipo punto de venta', '480.00'],
            ['DEMO-PRD-02', 'PRODUCTO', 'Impresora térmica', '195.00'],
            ['DEMO-PRD-03', 'PRODUCTO', 'Lector de código de barras', '72.50'],
            ['DEMO-PRD-04', 'PRODUCTO', 'Rollo de papel térmico', '3.80'],
            ['DEMO-PRD-05', 'PRODUCTO', 'Router empresarial', '135.00'],
            ['DEMO-PRD-06', 'PRODUCTO', 'Cajón monedero', '89.90'],
            ['DEMO-PRD-07', 'PRODUCTO', 'Regulador de voltaje', '42.00'],
        ];
        $ids = [];
        foreach ($catalog as $index => [$code, $type, $name, $price]) {
            $this->db->table('items')->insert([
                'codigo' => $code,
                'tipo' => $type,
                'nombre' => $name,
                'precio_referencia' => $price,
                'activo' => $index === 10 ? 0 : 1,
                'version' => 1,
            ]);
            $itemId = (int) $this->db->insertID();
            $ids[] = $itemId;
            $this->audit($actorId, 'catalogo.guardar', 'item:' . $itemId, ['origen' => 'datos_demo'], $today->modify('-35 days')->format('Y-m-d') . ' 15:00:00.000000');
        }

        return $ids;
    }

    /** @return list<int> */
    private function seedBankAccounts(): array
    {
        $rows = [
            ['Banco Pichincha', 'DEMO-210001', 'Cuenta principal'],
            ['Banco Guayaquil', 'DEMO-330002', 'Recaudación costa'],
        ];
        $ids = [];
        foreach ($rows as [$institution, $number, $alias]) {
            $this->db->table('cuentas_bancarias')->insert([
                'institucion' => $institution,
                'numero_cuenta' => $number,
                'alias' => $alias,
                'activa' => 1,
            ]);
            $ids[] = (int) $this->db->insertID();
        }

        return $ids;
    }

    /** @param list<int> $clients @param list<int> $items @param list<int> $accounts @return array{documents:int,payments:int,attachments:int} */
    private function seedPortfolio(int $actorId, \DateTimeImmutable $today, array $clients, array $items, array $accounts): array
    {
        $payments = [];
        $confirmedDocuments = [];
        for ($index = 0; $index < 18; $index++) {
            $clientId = $clients[$index % 15];
            $amount = number_format(180 + ($index * 47.5), 2, '.', '');
            $issuedOn = $today->modify('-' . (120 - ($index * 5)) . ' days')->format('Y-m-d');
            $creationOperation = $this->operation($actorId, 'demo:v1:documento:crear:' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'documentos.borrador.crear', $issuedOn, ['estado' => 'BORRADOR']);
            $this->db->table('obligaciones')->insert([
                'cliente_id' => $clientId,
                'origen' => 'VENTA',
                'concepto' => 'Venta de demostración ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'fecha_origen' => $issuedOn,
                'importe_base' => $amount,
                'operacion_creacion_id' => $creationOperation,
            ]);
            $obligationId = (int) $this->db->insertID();
            $this->db->table('documentos_obligacion')->insert([
                'obligacion_id' => $obligationId,
                'tipo' => $index % 5 === 0 ? 'NOTA_VENTA' : 'FACTURA',
                'numero_completo' => 'DEMO-001-001-' . str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT),
                'numero_normalizado' => 'DEMO001001' . str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT),
                'fecha_emision' => $issuedOn,
            ]);
            $this->db->table('obligacion_detalles')->insert([
                'obligacion_id' => $obligationId,
                'renglon' => 1,
                'item_id' => $items[$index % count($items)],
                'descripcion_pactada' => 'Concepto principal del documento de demostración',
                'cantidad' => '1.0000',
                'importe_linea_documentado' => $amount,
            ]);

            if ($index >= 15) {
                $this->audit($actorId, 'documentos.borrador.crear', 'obligacion:' . $obligationId, ['estado' => 'BORRADOR'], $issuedOn . ' 14:00:00.000000');
                continue;
            }

            $confirmationOperation = $this->operation($actorId, 'demo:v1:documento:confirmar:' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'documentos.confirmar', $issuedOn, ['estado' => 'CONFIRMADO']);
            $this->db->table('obligaciones')->where('id', $obligationId)->update(['operacion_confirmacion_id' => $confirmationOperation]);
            $installmentCount = ($index % 3) + 1;
            $baseCents = (int) round(((float) $amount) * 100);
            $regularCents = intdiv($baseCents, $installmentCount);
            $installments = [];
            for ($number = 1; $number <= $installmentCount; $number++) {
                $installmentCents = $number === $installmentCount ? $baseCents - ($regularCents * ($installmentCount - 1)) : $regularCents;
                $this->db->table('cuotas')->insert(['obligacion_id' => $obligationId, 'numero_cuota' => $number]);
                $installmentId = (int) $this->db->insertID();
                $dueDate = $today->modify(((-75 + ($index * 9)) + (($number - 1) * 30)) . ' days')->format('Y-m-d');
                $scheduled = number_format($installmentCents / 100, 2, '.', '');
                $this->db->table('cuota_versiones')->insert([
                    'cuota_id' => $installmentId,
                    'version' => 1,
                    'fecha_vencimiento' => $dueDate,
                    'importe_programado' => $scheduled,
                    'operacion_id' => $confirmationOperation,
                ]);
                $installments[] = ['id' => $installmentId, 'amount' => $scheduled];
            }
            $confirmedDocuments[] = ['id' => $obligationId, 'client_id' => $clientId, 'installments' => $installments];
            $this->audit($actorId, 'documentos.confirmar', 'obligacion:' . $obligationId, ['cuotas' => $installmentCount], $issuedOn . ' 15:00:00.000000');
        }

        foreach (array_slice($confirmedDocuments, 0, 9) as $index => $document) {
            $installment = $document['installments'][0];
            $scheduledCents = (int) round(((float) $installment['amount']) * 100);
            $paymentCents = $index % 3 === 0 ? intdiv($scheduledCents, 2) : $scheduledCents;
            $paymentAmount = number_format($paymentCents / 100, 2, '.', '');
            $paidOn = $today->modify('-' . (30 - ($index * 3)) . ' days')->format('Y-m-d');
            $operation = $this->operation($actorId, 'demo:v1:pago:' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'pagos.registrar', $paidOn, ['importe' => $paymentAmount]);
            $method = $index % 2 === 0 ? 'TRANSFERENCIA' : 'EFECTIVO';
            $this->db->table('pagos')->insert([
                'cliente_id' => $document['client_id'],
                'origen' => 'OPERATIVO',
                'medio_pago' => $method,
                'importe' => $paymentAmount,
                'fecha_declarada' => $paidOn,
                'operacion_confirmacion_id' => $operation,
            ]);
            $paymentId = (int) $this->db->insertID();
            if ($method === 'TRANSFERENCIA') {
                $this->db->table('pagos_bancarios')->insert([
                    'pago_id' => $paymentId,
                    'cuenta_bancaria_id' => $accounts[$index % count($accounts)],
                    'referencia' => 'TRX-DEMO-' . str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                    'fecha_bancaria' => $paidOn,
                    'evidencia_verificacion' => 'Registro de demostración',
                ]);
            }
            $this->db->table('aplicaciones_pago')->insert([
                'pago_id' => $paymentId,
                'cuota_id' => $installment['id'],
                'importe' => $paymentAmount,
                'operacion_id' => $operation,
            ]);
            $payments[] = ['id' => $paymentId, 'application_id' => (int) $this->db->insertID()];
            $this->audit($actorId, 'pagos.registrar', 'pago:' . $paymentId, ['importe' => $paymentAmount, 'medio' => $method], $paidOn . ' 16:00:00.000000');
        }

        $reverseOperation = $this->operation($actorId, 'demo:v1:pago:revertir:01', 'pagos.revertir', $today->modify('-2 days')->format('Y-m-d'), ['estado' => 'REVERTIDO']);
        $this->db->table('pago_reversiones')->insert([
            'pago_id' => $payments[8]['id'],
            'clase_correccion' => 'ANULACION_ADMINISTRATIVA',
            'operacion_id' => $reverseOperation,
        ]);
        $applicationReverseOperation = $this->operation($actorId, 'demo:v1:aplicacion:revertir:01', 'pagos.aplicaciones.revertir', $today->modify('-1 day')->format('Y-m-d'), ['estado' => 'REVERTIDA']);
        $this->db->table('aplicacion_reversiones')->insert([
            'aplicacion_id' => $payments[7]['application_id'],
            'operacion_id' => $applicationReverseOperation,
        ]);

        foreach (array_slice($confirmedDocuments, 0, 3) as $index => $document) {
            $this->seedAttachment($actorId, $document['id'], $index + 1, $today);
        }

        return ['documents' => 18, 'payments' => count($payments), 'attachments' => 3];
    }

    private function seedAttachment(int $actorId, int $obligationId, int $number, \DateTimeImmutable $today): void
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        if ($content === false) {
            throw new \RuntimeException('No se pudo preparar el adjunto de demostración.');
        }
        $relativeDirectory = 'documentos/' . $obligationId;
        $absoluteDirectory = WRITEPATH . 'uploads/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0770, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException('No se pudo crear el directorio privado de demostración.');
        }
        $storedName = 'demo-v1-comprobante-' . $number . '.png';
        $relativePath = $relativeDirectory . '/' . $storedName;
        $absolutePath = $absoluteDirectory . '/' . $storedName;
        if (file_put_contents($absolutePath, $content, LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo escribir el adjunto de demostración.');
        }
        $this->createdFiles[] = $absolutePath;
        $this->db->table('archivos_documento')->insert([
            'obligacion_id' => $obligationId,
            'nombre_original' => 'comprobante-demostracion-' . $number . '.png',
            'ruta_relativa' => $relativePath,
            'tipo_mime' => 'image/png',
            'extension' => 'png',
            'tamano_bytes' => strlen($content),
            'hash_sha256' => hash('sha256', $content),
            'notas' => 'Adjunto generado para probar el módulo de archivos.',
            'cargado_por' => $actorId,
            'cargado_en' => $today->modify('-' . $number . ' days')->format('Y-m-d') . ' 17:00:00.000000',
        ]);
    }

    private function operation(int $actorId, string $key, string $action, string $date, array $result): int
    {
        $timestamp = $date . ' 14:30:00.000000';
        $this->db->table('operaciones')->insert([
            'clave_idempotencia' => $key,
            'hash_solicitud' => hash('sha256', $key),
            'accion' => $action,
            'usuario_id' => $actorId,
            'origen' => 'SISTEMA',
            'efectiva_en' => $timestamp,
            'registrada_en' => $timestamp,
            'resultado_json' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->db->insertID();
    }

    private function audit(int $actorId, string $action, string $reference, array $detail, string $timestamp): void
    {
        $this->db->table('auditoria_eventos')->insert([
            'usuario_id' => $actorId,
            'registrado_en' => $timestamp,
            'accion' => $action,
            'referencia_textual' => $reference,
            'detalle_sanitizado' => json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
