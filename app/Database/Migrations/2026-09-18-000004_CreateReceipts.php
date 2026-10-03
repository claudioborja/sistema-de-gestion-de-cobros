<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateReceipts extends Migration
{
    public function up(): void
    {
        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

        $this->db->query("CREATE TABLE recibo_series (
            serie VARCHAR(15) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
            siguiente_numero BIGINT UNSIGNED NOT NULL,
            CHECK (siguiente_numero > 0)
        ){$options}");

        $this->db->query("CREATE TABLE recibos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            serie VARCHAR(15) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            numero BIGINT UNSIGNED NOT NULL,
            operacion_emision_id BIGINT UNSIGNED NOT NULL,
            cliente_id BIGINT UNSIGNED NOT NULL,
            contenido_json JSON NOT NULL,
            emitido_por INT UNSIGNED NOT NULL,
            emitido_en DATETIME(6) NOT NULL,
            UNIQUE KEY recibos_numero (serie, numero),
            UNIQUE KEY recibos_operacion (operacion_emision_id),
            INDEX recibos_cliente_fecha (cliente_id, emitido_en, id),
            FOREIGN KEY (serie) REFERENCES recibo_series(serie) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_emision_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (emitido_por) REFERENCES users(id) ON DELETE RESTRICT
        ){$options}");

        $this->db->query("CREATE TABLE recibo_pagos (
            recibo_id BIGINT UNSIGNED NOT NULL,
            pago_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (recibo_id, pago_id),
            UNIQUE KEY recibo_pagos_pago (pago_id),
            FOREIGN KEY (recibo_id) REFERENCES recibos(id) ON DELETE RESTRICT,
            FOREIGN KEY (pago_id) REFERENCES pagos(id) ON DELETE RESTRICT
        ){$options}");
    }

    public function down(): void
    {
        $this->forge->dropTable('recibo_pagos', true);
        $this->forge->dropTable('recibos', true);
        $this->forge->dropTable('recibo_series', true);
    }
}
