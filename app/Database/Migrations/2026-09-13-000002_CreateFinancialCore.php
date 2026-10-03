<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFinancialCore extends Migration
{
    public function up(): void
    {
        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

        $this->db->query("CREATE TABLE operaciones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            clave_idempotencia VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            hash_solicitud CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            accion VARCHAR(80) NOT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            origen VARCHAR(20) NOT NULL,
            efectiva_en DATETIME(6) NOT NULL,
            registrada_en DATETIME(6) NOT NULL,
            motivo VARCHAR(500) NULL,
            resultado_json JSON NULL,
            UNIQUE KEY operaciones_clave (clave_idempotencia),
            INDEX operaciones_usuario_fecha (usuario_id, registrada_en),
            FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE RESTRICT,
            CHECK (origen IN ('OPERATIVO','HISTORICO','APERTURA','SISTEMA'))
        ){$options}");

        $this->db->query("CREATE TABLE obligaciones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id BIGINT UNSIGNED NOT NULL,
            origen VARCHAR(20) NOT NULL,
            concepto VARCHAR(300) NOT NULL,
            fecha_origen DATE NOT NULL,
            importe_base DECIMAL(18,2) NOT NULL,
            operacion_creacion_id BIGINT UNSIGNED NOT NULL,
            operacion_confirmacion_id BIGINT UNSIGNED NULL,
            INDEX obligaciones_cliente_fecha (cliente_id, fecha_origen, id),
            UNIQUE KEY obligaciones_confirmacion (operacion_confirmacion_id),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_creacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_confirmacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (origen IN ('VENTA','PERIODO','APERTURA','HISTORICO')),
            CHECK (importe_base > 0)
        ){$options}");

        $this->db->query("CREATE TABLE documentos_obligacion (
            obligacion_id BIGINT UNSIGNED PRIMARY KEY,
            tipo VARCHAR(30) NOT NULL,
            numero_completo VARCHAR(100) NULL,
            numero_normalizado VARCHAR(100) NULL,
            fecha_emision DATE NULL,
            autorizacion_externa VARCHAR(120) NULL,
            UNIQUE KEY documentos_clave (tipo, numero_normalizado),
            FOREIGN KEY (obligacion_id) REFERENCES obligaciones(id) ON DELETE RESTRICT
        ){$options}");

        $this->db->query("CREATE TABLE obligacion_detalles (
            obligacion_id BIGINT UNSIGNED NOT NULL,
            renglon SMALLINT UNSIGNED NOT NULL,
            item_id BIGINT UNSIGNED NULL,
            descripcion_pactada VARCHAR(300) NOT NULL,
            cantidad DECIMAL(18,4) NOT NULL,
            importe_linea_documentado DECIMAL(18,2) NOT NULL,
            PRIMARY KEY (obligacion_id, renglon),
            INDEX obligacion_detalles_item (item_id),
            FOREIGN KEY (obligacion_id) REFERENCES obligaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT,
            CHECK (cantidad > 0),
            CHECK (importe_linea_documentado >= 0)
        ){$options}");

        $this->db->query("CREATE TABLE cuotas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            obligacion_id BIGINT UNSIGNED NOT NULL,
            numero_cuota SMALLINT UNSIGNED NOT NULL,
            UNIQUE KEY cuotas_numero (obligacion_id, numero_cuota),
            FOREIGN KEY (obligacion_id) REFERENCES obligaciones(id) ON DELETE RESTRICT
        ){$options}");

        $this->db->query("CREATE TABLE cuota_versiones (
            cuota_id BIGINT UNSIGNED NOT NULL,
            version SMALLINT UNSIGNED NOT NULL,
            fecha_vencimiento DATE NULL,
            importe_programado DECIMAL(18,2) NOT NULL,
            operacion_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (cuota_id, version),
            INDEX cuota_versiones_operacion (operacion_id),
            INDEX cuota_versiones_vencimiento (fecha_vencimiento, cuota_id),
            FOREIGN KEY (cuota_id) REFERENCES cuotas(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (importe_programado > 0)
        ){$options}");

        $this->db->query("CREATE TABLE cuentas_bancarias (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            institucion VARCHAR(120) NOT NULL,
            numero_cuenta VARCHAR(80) NOT NULL,
            alias VARCHAR(100) NOT NULL,
            activa TINYINT NOT NULL DEFAULT 1,
            UNIQUE KEY cuentas_bancarias_numero (institucion, numero_cuenta),
            CHECK (activa IN (0,1))
        ){$options}");

        $this->db->query("CREATE TABLE pagos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id BIGINT UNSIGNED NOT NULL,
            origen VARCHAR(20) NOT NULL,
            medio_pago VARCHAR(20) NULL,
            importe DECIMAL(18,2) NOT NULL,
            fecha_declarada DATE NULL,
            operacion_confirmacion_id BIGINT UNSIGNED NOT NULL,
            INDEX pagos_cliente_fecha (cliente_id, id),
            UNIQUE KEY pagos_operacion (operacion_confirmacion_id),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_confirmacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (origen IN ('OPERATIVO','HISTORICO','APERTURA')),
            CHECK (medio_pago IS NULL OR medio_pago IN ('EFECTIVO','TRANSFERENCIA','DEPOSITO')),
            CHECK (importe > 0)
        ){$options}");

        $this->db->query("CREATE TABLE pagos_bancarios (
            pago_id BIGINT UNSIGNED PRIMARY KEY,
            cuenta_bancaria_id BIGINT UNSIGNED NOT NULL,
            referencia VARCHAR(120) NOT NULL,
            fecha_bancaria DATE NULL,
            evidencia_verificacion VARCHAR(500) NULL,
            INDEX pagos_bancarios_cuenta_fecha (cuenta_bancaria_id, fecha_bancaria, pago_id),
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE RESTRICT,
            FOREIGN KEY (cuenta_bancaria_id) REFERENCES cuentas_bancarias(id) ON DELETE RESTRICT
        ){$options}");

        $this->db->query("CREATE TABLE aplicaciones_pago (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pago_id BIGINT UNSIGNED NOT NULL,
            cuota_id BIGINT UNSIGNED NOT NULL,
            importe DECIMAL(18,2) NOT NULL,
            operacion_id BIGINT UNSIGNED NOT NULL,
            INDEX aplicaciones_pago_pago (pago_id, id),
            INDEX aplicaciones_pago_cuota (cuota_id, id),
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE RESTRICT,
            FOREIGN KEY (cuota_id) REFERENCES cuotas(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (importe > 0)
        ){$options}");

        $this->db->query("CREATE TABLE aplicacion_reversiones (
            aplicacion_id BIGINT UNSIGNED PRIMARY KEY,
            operacion_id BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY aplicacion_reversion_operacion (operacion_id),
            FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones_pago(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT
        ){$options}");

        $this->db->query("CREATE TABLE pago_reversiones (
            pago_id BIGINT UNSIGNED PRIMARY KEY,
            clase_correccion VARCHAR(30) NOT NULL,
            operacion_id BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY pago_reversion_operacion (operacion_id),
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT
        ){$options}");
    }

    public function down(): void
    {
        foreach (['pago_reversiones', 'aplicacion_reversiones', 'aplicaciones_pago', 'pagos_bancarios', 'pagos', 'cuentas_bancarias', 'cuota_versiones', 'cuotas', 'obligacion_detalles', 'documentos_obligacion', 'obligaciones', 'operaciones'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
