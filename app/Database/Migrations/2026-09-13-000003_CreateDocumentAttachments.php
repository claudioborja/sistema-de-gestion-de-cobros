<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDocumentAttachments extends Migration
{
    public function up(): void
    {
        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

        $this->db->query("CREATE TABLE archivos_documento (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            obligacion_id BIGINT UNSIGNED NOT NULL,
            nombre_original VARCHAR(190) NOT NULL,
            ruta_relativa VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            tipo_mime VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            extension VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            tamano_bytes INT UNSIGNED NOT NULL,
            hash_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            notas VARCHAR(500) NULL,
            cargado_por INT UNSIGNED NOT NULL,
            cargado_en DATETIME(6) NOT NULL,
            UNIQUE KEY archivos_documento_ruta (ruta_relativa),
            INDEX archivos_documento_obligacion_fecha (obligacion_id, cargado_en, id),
            INDEX archivos_documento_hash (hash_sha256),
            FOREIGN KEY (obligacion_id) REFERENCES obligaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (cargado_por) REFERENCES users(id) ON DELETE RESTRICT,
            CHECK (tamano_bytes > 0),
            CHECK (extension IN ('pdf','png','jpg'))
        ){$options}");
    }

    public function down(): void
    {
        $this->forge->dropTable('archivos_documento', true);
    }
}
