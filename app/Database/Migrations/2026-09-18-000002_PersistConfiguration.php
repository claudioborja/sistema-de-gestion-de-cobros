<?php
declare(strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class PersistConfiguration extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE cuentas_bancarias ADD titular VARCHAR(120) NOT NULL DEFAULT '', ADD tipo VARCHAR(20) NOT NULL DEFAULT 'OTRA'");
        $this->db->query("CREATE TABLE configuracion_sistema (
            clave VARCHAR(80) PRIMARY KEY,
            valor JSON NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    }

    public function down(): void
    {
        $this->forge->dropTable('configuracion_sistema', true);
        $this->forge->dropColumn('cuentas_bancarias', ['titular', 'tipo']);
    }
}
