<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateRolePermissionsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE role_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                role_id BIGINT UNSIGNED NOT NULL,

                permission_id BIGINT UNSIGNED NOT NULL,

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_role_permissions_role_permission
                    (role_id, permission_id),

                KEY idx_role_permissions_role
                    (role_id),

                KEY idx_role_permissions_permission
                    (permission_id),

                CONSTRAINT fk_role_permissions_role
                    FOREIGN KEY (role_id)
                    REFERENCES roles(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,

                CONSTRAINT fk_role_permissions_permission
                    FOREIGN KEY (permission_id)
                    REFERENCES permissions(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS role_permissions
        ");
    }
}

return new CreateRolePermissionsTable();