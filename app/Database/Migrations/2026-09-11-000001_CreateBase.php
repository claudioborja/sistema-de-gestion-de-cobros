<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class CreateBase extends Migration
{
    public function up(): void
    {
        $options=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci';
        $this->db->query('CREATE TABLE clientes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(160) NOT NULL, direccion VARCHAR(240) NULL, activo TINYINT NOT NULL DEFAULT 1, version INT UNSIGNED NOT NULL DEFAULT 1, creado_en DATETIME(6) NOT NULL, INDEX clientes_nombre(nombre), CHECK (activo IN (0,1)))'.$options);
        $this->db->query('CREATE TABLE cliente_identificaciones (pais_emisor CHAR(2) NOT NULL, tipo VARCHAR(20) NOT NULL, numero_normalizado VARCHAR(40) NOT NULL, cliente_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(pais_emisor,tipo,numero_normalizado), INDEX(cliente_id), FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT)'.$options);
        $this->db->query('CREATE TABLE cliente_contactos (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cliente_id BIGINT UNSIGNED NOT NULL, canal VARCHAR(20) NOT NULL, valor VARCHAR(190) NOT NULL, etiqueta VARCHAR(40) NULL, habilitado_cobranza TINYINT NOT NULL DEFAULT 0, UNIQUE(cliente_id,canal,valor), FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT)'.$options);
        $this->db->query('CREATE TABLE items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(40) NOT NULL UNIQUE, tipo VARCHAR(10) NOT NULL, nombre VARCHAR(160) NOT NULL, precio_referencia DECIMAL(18,2) NULL, activo TINYINT NOT NULL DEFAULT 1, version INT UNSIGNED NOT NULL DEFAULT 1, CHECK (tipo IN (\'PRODUCTO\',\'SERVICIO\')), CHECK (precio_referencia IS NULL OR precio_referencia >= 0), CHECK (activo IN (0,1)))'.$options);
        $this->db->query('CREATE TABLE auditoria_eventos (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id INT UNSIGNED NULL, registrado_en DATETIME(6) NOT NULL, accion VARCHAR(80) NOT NULL, referencia_textual VARCHAR(100) NOT NULL, detalle_sanitizado JSON NOT NULL, INDEX(registrado_en), FOREIGN KEY(usuario_id) REFERENCES users(id) ON DELETE RESTRICT)'.$options);
    }
    public function down(): void
    {
        foreach (['auditoria_eventos','cliente_contactos','cliente_identificaciones','items','clientes'] as $table) { $this->forge->dropTable($table, true); }
    }
}
