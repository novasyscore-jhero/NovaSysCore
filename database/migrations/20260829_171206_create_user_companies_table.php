<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateUserCompaniesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE user_companies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                user_id BIGINT UNSIGNED NOT NULL,

                company_id BIGINT UNSIGNED NOT NULL,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_user_companies_user_company
                    (user_id, company_id),

                KEY idx_user_companies_user
                    (user_id),

                KEY idx_user_companies_company
                    (company_id),

                KEY idx_user_companies_status
                    (status),

                CONSTRAINT fk_user_companies_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_user_companies_company
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
            DROP TABLE IF EXISTS user_companies
        ");
    }
}

return new CreateUserCompaniesTable();