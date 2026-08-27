<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateCompanyAddressesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE company_addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                company_id BIGINT UNSIGNED NOT NULL,

                address_id BIGINT UNSIGNED NOT NULL,

                is_primary BOOLEAN NOT NULL DEFAULT FALSE,

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_company_addresses_company_address
                    (company_id, address_id),

                KEY idx_company_addresses_company
                    (company_id),

                KEY idx_company_addresses_address
                    (address_id),

                KEY idx_company_addresses_primary
                    (company_id, is_primary),

                CONSTRAINT fk_company_addresses_company
                    FOREIGN KEY (company_id)
                    REFERENCES companies(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_company_addresses_address
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
            DROP TABLE IF EXISTS company_addresses
        ");
    }
}

return new CreateCompanyAddressesTable();