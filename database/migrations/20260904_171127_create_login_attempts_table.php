<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateLoginAttemptsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE login_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                user_id BIGINT UNSIGNED NULL,

                identifier_hash CHAR(64) NOT NULL,

                ip_address VARCHAR(45) NOT NULL,

                user_agent_hash CHAR(64) NULL,

                was_successful TINYINT(1) NOT NULL DEFAULT 0,

                was_blocked TINYINT(1) NOT NULL DEFAULT 0,

                failure_reason VARCHAR(50) NULL,

                attempted_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                KEY idx_login_attempts_user_time (
                    user_id,
                    attempted_at
                ),

                KEY idx_login_attempts_identifier_time (
                    identifier_hash,
                    attempted_at
                ),

                KEY idx_login_attempts_ip_time (
                    ip_address,
                    attempted_at
                ),

                KEY idx_login_attempts_success_time (
                    was_successful,
                    attempted_at
                ),

                KEY idx_login_attempts_blocked_time (
                    was_blocked,
                    attempted_at
                ),

                CONSTRAINT fk_login_attempts_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS login_attempts
        ");
    }
}

return new CreateLoginAttemptsTable();