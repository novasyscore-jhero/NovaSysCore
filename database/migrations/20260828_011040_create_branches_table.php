<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateBranchesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE branches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                company_id BIGINT UNSIGNED NOT NULL,

                address_id BIGINT UNSIGNED NULL,

                name VARCHAR(150) NOT NULL,

                code VARCHAR(50) NOT NULL,

                slug VARCHAR(180) NOT NULL,

                description TEXT NULL,

                email VARCHAR(150) NULL,

                phone VARCHAR(50) NULL,

                timezone VARCHAR(50) NULL,

                locale VARCHAR(10) NULL,

                is_main BOOLEAN NOT NULL DEFAULT FALSE,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_branches_company_code
                    (company_id, code),

                UNIQUE KEY uq_branches_company_slug
                    (company_id, slug),

                KEY idx_branches_company
                    (company_id),

                KEY idx_branches_address
                    (address_id),

                KEY idx_branches_status
                    (status),

                KEY idx_branches_main
                    (company_id, is_main),

                CONSTRAINT fk_branches_company
                    FOREIGN KEY (company_id)
                    REFERENCES companies(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_branches_address
                    FOREIGN KEY (address_id)
                    REFERENCES addresses(id)
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
            DROP TABLE IF EXISTS branches
        ");
    }
}

return new CreateBranchesTable();