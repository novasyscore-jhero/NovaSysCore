<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateUserSystemRolesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE user_system_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                user_id BIGINT UNSIGNED NOT NULL,

                role_id BIGINT UNSIGNED NOT NULL,

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_user_system_roles_user_role
                    (user_id, role_id),

                KEY idx_user_system_roles_user
                    (user_id),

                KEY idx_user_system_roles_role
                    (role_id),

                CONSTRAINT fk_user_system_roles_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_user_system_roles_role
                    FOREIGN KEY (role_id)
                    REFERENCES roles(id)
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
            DROP TABLE IF EXISTS user_system_roles
        ");
    }
}

return new CreateUserSystemRolesTable();