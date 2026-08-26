<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateAdministrativeDivisionsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE administrative_divisions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                country_id BIGINT UNSIGNED NOT NULL,

                name VARCHAR(150) NOT NULL,

                code VARCHAR(20) NULL,

                type VARCHAR(50) NOT NULL,

                slug VARCHAR(180) NOT NULL,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_admin_divisions_country_code
                    (country_id, code),

                UNIQUE KEY uq_admin_divisions_country_slug
                    (country_id, slug),

                KEY idx_admin_divisions_country
                    (country_id),

                CONSTRAINT fk_admin_divisions_country
                    FOREIGN KEY (country_id)
                    REFERENCES countries(id)
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
            DROP TABLE IF EXISTS administrative_divisions
        ");
    }
}

return new CreateAdministrativeDivisionsTable();