<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateBankReconciliation extends Migration
{
    public function up(): void
    {
        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

        $this->db->query("CREATE TABLE reportes_pago (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id BIGINT UNSIGNED NULL,
            cuenta_bancaria_id BIGINT UNSIGNED NOT NULL,
            metodo VARCHAR(20) NOT NULL,
            importe_reportado DECIMAL(18,2) NOT NULL,
            fecha_reportada DATE NOT NULL,
            referencia_reportada VARCHAR(120) NOT NULL,
            observacion VARCHAR(500) NULL,
            operacion_id BIGINT UNSIGNED NOT NULL,
            reportado_en DATETIME(6) NOT NULL,
            INDEX reportes_pago_estado_fecha (fecha_reportada, id),
            INDEX reportes_pago_cliente_fecha (cliente_id, fecha_reportada, id),
            INDEX reportes_pago_cuenta_fecha (cuenta_bancaria_id, fecha_reportada, id),
            UNIQUE KEY reportes_pago_operacion (operacion_id),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (cuenta_bancaria_id) REFERENCES cuentas_bancarias(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (metodo IN ('TRANSFERENCIA','DEPOSITO')),
            CHECK (importe_reportado > 0)
        ){$options}");

        $this->db->query("CREATE TABLE reporte_archivos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reporte_id BIGINT UNSIGNED NOT NULL,
            nombre_original VARCHAR(190) NOT NULL,
            ruta_relativa VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            tipo_mime VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            extension VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            tamano_bytes INT UNSIGNED NOT NULL,
            hash_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            cargado_por INT UNSIGNED NOT NULL,
            cargado_en DATETIME(6) NOT NULL,
            UNIQUE KEY reporte_archivos_ruta (ruta_relativa),
            INDEX reporte_archivos_reporte (reporte_id, id),
            FOREIGN KEY (reporte_id) REFERENCES reportes_pago(id) ON DELETE RESTRICT,
            FOREIGN KEY (cargado_por) REFERENCES users(id) ON DELETE RESTRICT,
            CHECK (tamano_bytes > 0),
            CHECK (extension IN ('pdf','png','jpg'))
        ){$options}");

        $this->db->query("CREATE TABLE reporte_revisiones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reporte_id BIGINT UNSIGNED NOT NULL,
            revision SMALLINT UNSIGNED NOT NULL,
            resultado VARCHAR(20) NOT NULL,
            pago_id BIGINT UNSIGNED NULL,
            notas VARCHAR(500) NOT NULL,
            operacion_id BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY reporte_revision_numero (reporte_id, revision),
            UNIQUE KEY reporte_revision_operacion (operacion_id),
            UNIQUE KEY reporte_revision_pago (pago_id),
            FOREIGN KEY (reporte_id) REFERENCES reportes_pago(id) ON DELETE RESTRICT,
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (resultado IN ('APROBADO','VINCULADO','RECHAZADO','REABIERTO')),
            CHECK ((resultado IN ('APROBADO','VINCULADO') AND pago_id IS NOT NULL) OR (resultado IN ('RECHAZADO','REABIERTO') AND pago_id IS NULL))
        ){$options}");
    }

    public function down(): void
    {
        foreach (['reporte_revisiones', 'reporte_archivos', 'reportes_pago'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
