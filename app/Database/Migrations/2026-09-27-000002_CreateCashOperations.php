<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCashOperations extends Migration
{
    public function up(): void
    {
        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

        $this->db->query("CREATE TABLE turnos_caja (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            caja_id BIGINT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            fondo_inicial DECIMAL(18,2) NOT NULL,
            estado VARCHAR(20) NOT NULL,
            abierto_en DATETIME(6) NOT NULL,
            cerrado_en DATETIME(6) NULL,
            efectivo_contado DECIMAL(18,2) NULL,
            diferencia DECIMAL(18,2) NULL,
            motivo_diferencia VARCHAR(500) NULL,
            operacion_apertura_id BIGINT UNSIGNED NOT NULL,
            operacion_cierre_id BIGINT UNSIGNED NULL,
            caja_abierta_id BIGINT UNSIGNED NULL,
            usuario_abierto_id INT UNSIGNED NULL,
            INDEX turnos_caja_historial (caja_id, abierto_en, id),
            INDEX turnos_usuario_historial (usuario_id, abierto_en, id),
            UNIQUE KEY turnos_operacion_apertura (operacion_apertura_id),
            UNIQUE KEY turnos_operacion_cierre (operacion_cierre_id),
            UNIQUE KEY turnos_caja_abierta (caja_abierta_id),
            UNIQUE KEY turnos_usuario_abierto (usuario_abierto_id),
            FOREIGN KEY (caja_id) REFERENCES cajas(id) ON DELETE RESTRICT,
            FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_apertura_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_cierre_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (caja_abierta_id) REFERENCES cajas(id) ON DELETE RESTRICT,
            FOREIGN KEY (usuario_abierto_id) REFERENCES users(id) ON DELETE RESTRICT,
            CHECK (fondo_inicial >= 0),
            CHECK (estado IN ('ABIERTO','CERRADO')),
            CHECK ((estado = 'ABIERTO' AND cerrado_en IS NULL AND efectivo_contado IS NULL AND diferencia IS NULL
                    AND caja_abierta_id = caja_id AND usuario_abierto_id = usuario_id)
                OR (estado = 'CERRADO' AND cerrado_en IS NOT NULL AND efectivo_contado IS NOT NULL AND diferencia IS NOT NULL
                    AND caja_abierta_id IS NULL AND usuario_abierto_id IS NULL))
        ){$options}");

        $this->db->query("CREATE TABLE movimientos_caja (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            turno_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            importe DECIMAL(18,2) NOT NULL,
            concepto VARCHAR(300) NOT NULL,
            pago_id BIGINT UNSIGNED NULL,
            operacion_id BIGINT UNSIGNED NOT NULL,
            registrado_por INT UNSIGNED NOT NULL,
            registrado_en DATETIME(6) NOT NULL,
            INDEX movimientos_turno_fecha (turno_id, registrado_en, id),
            UNIQUE KEY movimientos_pago (pago_id),
            UNIQUE KEY movimientos_operacion (operacion_id),
            FOREIGN KEY (turno_id) REFERENCES turnos_caja(id) ON DELETE RESTRICT,
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (registrado_por) REFERENCES users(id) ON DELETE RESTRICT,
            CHECK (tipo IN ('APORTE','COBRO','RETIRO','GASTO','DEVOLUCION')),
            CHECK (importe > 0)
        ){$options}");

        $this->db->query("CREATE TABLE entregas_turno (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            turno_id BIGINT UNSIGNED NOT NULL,
            entregado_por INT UNSIGNED NOT NULL,
            recibido_por INT UNSIGNED NOT NULL,
            importe_entregado DECIMAL(18,2) NOT NULL,
            fondo_remanente DECIMAL(18,2) NOT NULL,
            estado VARCHAR(20) NOT NULL,
            observacion_entrega VARCHAR(500) NULL,
            observacion_recepcion VARCHAR(500) NULL,
            operacion_entrega_id BIGINT UNSIGNED NOT NULL,
            operacion_recepcion_id BIGINT UNSIGNED NULL,
            entregado_en DATETIME(6) NOT NULL,
            recibido_en DATETIME(6) NULL,
            UNIQUE KEY entregas_turno_unica (turno_id),
            UNIQUE KEY entregas_operacion_entrega (operacion_entrega_id),
            UNIQUE KEY entregas_operacion_recepcion (operacion_recepcion_id),
            INDEX entregas_receptor_estado (recibido_por, estado, entregado_en),
            FOREIGN KEY (turno_id) REFERENCES turnos_caja(id) ON DELETE RESTRICT,
            FOREIGN KEY (entregado_por) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (recibido_por) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_entrega_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_recepcion_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (entregado_por <> recibido_por),
            CHECK (importe_entregado >= 0),
            CHECK (fondo_remanente >= 0),
            CHECK (estado IN ('PENDIENTE','RECIBIDA')),
            CHECK ((estado = 'PENDIENTE' AND operacion_recepcion_id IS NULL AND recibido_en IS NULL)
                OR (estado = 'RECIBIDA' AND operacion_recepcion_id IS NOT NULL AND recibido_en IS NOT NULL))
        ){$options}");
    }

    public function down(): void
    {
        $this->forge->dropTable('entregas_turno', true);
        $this->forge->dropTable('movimientos_caja', true);
        $this->forge->dropTable('turnos_caja', true);
    }
}
