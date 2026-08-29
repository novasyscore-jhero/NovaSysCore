<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                name VARCHAR(100) NOT NULL,

                last_name VARCHAR(150) NULL,

                display_name VARCHAR(150) NULL,

                email VARCHAR(180) NOT NULL,

                password_hash VARCHAR(255) NOT NULL,

                phone VARCHAR(50) NULL,

                avatar VARCHAR(255) NULL,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                email_verified_at DATETIME NULL,

                last_login_at DATETIME NULL,

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_users_email (email),

                KEY idx_users_status (status),

                KEY idx_users_last_login (last_login_at)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS users
        ");
    }
}

return new CreateUsersTable();