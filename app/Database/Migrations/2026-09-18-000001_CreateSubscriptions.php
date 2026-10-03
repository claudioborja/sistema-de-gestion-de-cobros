<?php

declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class CreateSubscriptions extends Migration
{
    public function up(): void
    {
        $options=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';
        $this->db->query("CREATE TABLE contratos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id BIGINT UNSIGNED NOT NULL, item_id BIGINT UNSIGNED NOT NULL,
            servicio VARCHAR(160) NOT NULL, fecha_inicio DATE NOT NULL, fecha_fin_pactada DATE NULL,
            frecuencia_meses SMALLINT UNSIGNED NOT NULL, politica_calendario VARCHAR(20) NOT NULL,
            operacion_id BIGINT UNSIGNED NOT NULL,
            INDEX contratos_cliente (cliente_id,id),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id), FOREIGN KEY (item_id) REFERENCES items(id),
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id),
            CHECK (frecuencia_meses IN (1,12)), CHECK (fecha_fin_pactada IS NULL OR fecha_fin_pactada > fecha_inicio),
            CHECK (politica_calendario IN ('ANCLA','PROPORCIONAL','COMPLETO'))
        ){$options}");
        $this->db->query("CREATE TABLE contrato_condiciones (
            contrato_id BIGINT UNSIGNED NOT NULL, version SMALLINT UNSIGNED NOT NULL,
            vigente_desde DATE NOT NULL, importe_periodo DECIMAL(18,2) NOT NULL,
            momento_cobro VARCHAR(20) NOT NULL, plazo_dias SMALLINT UNSIGNED NOT NULL,
            requiere_aceptacion TINYINT NOT NULL, operacion_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (contrato_id,version), UNIQUE KEY condiciones_fecha (contrato_id,vigente_desde),
            FOREIGN KEY (contrato_id) REFERENCES contratos(id), FOREIGN KEY (operacion_id) REFERENCES operaciones(id),
            CHECK (importe_periodo > 0), CHECK (momento_cobro IN ('ANTICIPADO','VENCIDO')),
            CHECK (plazo_dias <= 365), CHECK (requiere_aceptacion IN (0,1))
        ){$options}");
        $this->db->query("CREATE TABLE contrato_eventos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contrato_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(20) NOT NULL, fecha_efectiva DATE NOT NULL, operacion_id BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY eventos_fecha (contrato_id,fecha_efectiva),
            FOREIGN KEY (contrato_id) REFERENCES contratos(id), FOREIGN KEY (operacion_id) REFERENCES operaciones(id),
            CHECK (tipo IN ('PAUSA','REACTIVACION','CANCELACION'))
        ){$options}");
        $this->db->query("CREATE TABLE periodos_contrato (
            contrato_id BIGINT UNSIGNED NOT NULL, periodo_desde DATE NOT NULL, periodo_hasta DATE NOT NULL,
            condicion_version SMALLINT UNSIGNED NOT NULL, obligacion_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (contrato_id,periodo_desde), UNIQUE KEY periodo_obligacion (obligacion_id),
            FOREIGN KEY (contrato_id,condicion_version) REFERENCES contrato_condiciones(contrato_id,version),
            FOREIGN KEY (obligacion_id) REFERENCES obligaciones(id), CHECK (periodo_hasta > periodo_desde)
        ){$options}");
        $this->db->query("CREATE TABLE renovacion_decisiones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contrato_id BIGINT UNSIGNED NOT NULL,
            periodo_desde DATE NOT NULL, condicion_version SMALLINT UNSIGNED NOT NULL,
            evidencia VARCHAR(1000) NOT NULL, operacion_id BIGINT UNSIGNED NOT NULL,
            UNIQUE KEY renovacion_periodo_version (contrato_id,periodo_desde,condicion_version),
            FOREIGN KEY (contrato_id,condicion_version) REFERENCES contrato_condiciones(contrato_id,version),
            FOREIGN KEY (operacion_id) REFERENCES operaciones(id)
        ){$options}");
    }
    public function down(): void
    {
        foreach (['renovacion_decisiones','periodos_contrato','contrato_eventos','contrato_condiciones','contratos'] as $table) {
            $this->forge->dropTable($table,true);
        }
    }
}
