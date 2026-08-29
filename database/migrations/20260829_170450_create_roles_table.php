<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateRolesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                company_id BIGINT UNSIGNED NULL,

                name VARCHAR(120) NOT NULL,

                slug VARCHAR(150) NOT NULL,

                description VARCHAR(255) NULL,

                is_system BOOLEAN NOT NULL DEFAULT FALSE,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_roles_company_slug
                    (company_id, slug),

                KEY idx_roles_company
                    (company_id),

                KEY idx_roles_status
                    (status),

                KEY idx_roles_is_system
                    (is_system),

                CONSTRAINT fk_roles_company
                    FOREIGN KEY (company_id)
                    REFERENCES companies(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS roles
        ");
    }
}

return new CreateRolesTable();