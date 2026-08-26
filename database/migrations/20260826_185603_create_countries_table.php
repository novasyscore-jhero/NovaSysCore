<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateCountriesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE countries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                name VARCHAR(100) NOT NULL,

                official_name VARCHAR(200) NULL,

                iso2 CHAR(2) NOT NULL,

                iso3 CHAR(3) NOT NULL,

                numeric_code CHAR(3) NULL,

                phone_code VARCHAR(10) NULL,

                currency CHAR(3) NULL,

                locale VARCHAR(10) NULL,

                timezone VARCHAR(50) NULL,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_countries_iso2 (iso2),
                UNIQUE KEY uq_countries_iso3 (iso3),
                UNIQUE KEY uq_countries_numeric_code (numeric_code)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS countries
        ");
    }
}

return new CreateCountriesTable();