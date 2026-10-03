<?php

declare(strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCashRegisters extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE cajas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(30) NOT NULL,
            nombre VARCHAR(120) NOT NULL,
            activa TINYINT UNSIGNED NOT NULL DEFAULT 1,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            creado_en DATETIME NOT NULL,
            actualizado_en DATETIME NOT NULL,
            UNIQUE KEY cajas_codigo (codigo),
            CHECK (activa IN (0, 1)),
            CHECK (version > 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    }

    public function down(): void
    {
        $this->forge->dropTable('cajas', true);
    }
}
