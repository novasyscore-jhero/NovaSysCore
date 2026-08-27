<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateCompaniesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE companies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                group_id BIGINT UNSIGNED NULL,

                name VARCHAR(150) NOT NULL,

                legal_name VARCHAR(200) NULL,

                slug VARCHAR(180) NOT NULL,

                code VARCHAR(50) NOT NULL,

                tax_id VARCHAR(50) NULL,

                tax_regime VARCHAR(100) NULL,

                email VARCHAR(150) NULL,

                phone VARCHAR(50) NULL,

                website VARCHAR(180) NULL,

                logo VARCHAR(255) NULL,

                description TEXT NULL,

                locale VARCHAR(10) NOT NULL DEFAULT 'es-MX',

                currency CHAR(3) NOT NULL DEFAULT 'MXN',

                timezone VARCHAR(50) NOT NULL DEFAULT 'America/Mexico_City',

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_companies_slug (slug),

                UNIQUE KEY uq_companies_code (code),

                KEY idx_companies_group (group_id),

                KEY idx_companies_tax_id (tax_id),

                KEY idx_companies_status (status),

                CONSTRAINT fk_companies_group
                    FOREIGN KEY (group_id)
                    REFERENCES groups(id)
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
            DROP TABLE IF EXISTS companies
        ");
    }
}

return new CreateCompaniesTable();