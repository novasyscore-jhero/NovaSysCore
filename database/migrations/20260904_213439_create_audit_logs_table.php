<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                user_id BIGINT UNSIGNED NULL,

                action VARCHAR(100) NOT NULL,

                table_name VARCHAR(100) NULL,

                record_id BIGINT UNSIGNED NULL,

                company_id BIGINT UNSIGNED NULL,

                branch_id BIGINT UNSIGNED NULL,

                ip_address VARCHAR(45) NULL,

                user_agent_hash CHAR(64) NULL,

                old_values LONGTEXT NULL,

                new_values LONGTEXT NULL,

                metadata LONGTEXT NULL,

                created_at DATETIME NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,

                KEY idx_audit_logs_user (
                    user_id
                ),

                KEY idx_audit_logs_action (
                    action
                ),

                KEY idx_audit_logs_table_record (
                    table_name,
                    record_id
                ),

                KEY idx_audit_logs_company (
                    company_id
                ),

                KEY idx_audit_logs_branch (
                    branch_id
                ),

                KEY idx_audit_logs_created_at (
                    created_at
                ),

                CONSTRAINT fk_audit_logs_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,

                CONSTRAINT fk_audit_logs_company
                    FOREIGN KEY (company_id)
                    REFERENCES companies(id)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,

                CONSTRAINT fk_audit_logs_branch
                    FOREIGN KEY (branch_id)
                    REFERENCES branches(id)
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
            DROP TABLE IF EXISTS audit_logs
        ");
    }
}

return new CreateAuditLogsTable();