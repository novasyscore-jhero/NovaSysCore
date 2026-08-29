<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreatePermissionsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                name VARCHAR(150) NOT NULL,

                slug VARCHAR(180) NOT NULL,

                description VARCHAR(255) NULL,

                module VARCHAR(100) NOT NULL,

                action VARCHAR(100) NOT NULL,

                is_system BOOLEAN NOT NULL DEFAULT TRUE,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_permissions_slug
                    (slug),

                UNIQUE KEY uq_permissions_module_action
                    (module, action),

                KEY idx_permissions_module
                    (module),

                KEY idx_permissions_action
                    (action),

                KEY idx_permissions_status
                    (status),

                KEY idx_permissions_is_system
                    (is_system)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS permissions
        ");
    }
}

return new CreatePermissionsTable();