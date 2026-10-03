<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCustomerPortal extends Migration
{
    public function up(): void
    {
        $options = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';

        $this->db->query("CREATE TABLE cliente_invitaciones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cliente_id BIGINT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            expira_en DATETIME(6) NOT NULL,
            creada_por INT UNSIGNED NOT NULL,
            creada_en DATETIME(6) NOT NULL,
            usada_en DATETIME(6) NULL,
            revocada_en DATETIME(6) NULL,
            operacion_uso_id BIGINT UNSIGNED NULL,
            UNIQUE KEY invitaciones_token (token_hash),
            UNIQUE KEY invitaciones_operacion_uso (operacion_uso_id),
            INDEX invitaciones_usuario_estado (usuario_id, usada_en, revocada_en, expira_en),
            INDEX invitaciones_cliente_fecha (cliente_id, creada_en, id),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (creada_por) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_uso_id) REFERENCES operaciones(id) ON DELETE RESTRICT,
            CHECK (usada_en IS NULL OR revocada_en IS NULL)
        ){$options}");

        $this->db->query("CREATE TABLE cliente_usuarios (
            usuario_id INT UNSIGNED PRIMARY KEY,
            cliente_id BIGINT UNSIGNED NOT NULL,
            invitacion_id BIGINT UNSIGNED NOT NULL,
            operacion_vinculacion_id BIGINT UNSIGNED NOT NULL,
            vinculado_en DATETIME(6) NOT NULL,
            INDEX cliente_usuarios_cliente (cliente_id, usuario_id),
            UNIQUE KEY cliente_usuarios_invitacion (invitacion_id),
            UNIQUE KEY cliente_usuarios_operacion (operacion_vinculacion_id),
            FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
            FOREIGN KEY (invitacion_id) REFERENCES cliente_invitaciones(id) ON DELETE RESTRICT,
            FOREIGN KEY (operacion_vinculacion_id) REFERENCES operaciones(id) ON DELETE RESTRICT
        ){$options}");
    }

    public function down(): void
    {
        $this->forge->dropTable('cliente_usuarios', true);
        $this->forge->dropTable('cliente_invitaciones', true);
    }
}
