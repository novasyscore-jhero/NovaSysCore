<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreatePostalCodesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE postal_codes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                country_id BIGINT UNSIGNED NOT NULL,

                administrative_division_id BIGINT UNSIGNED NULL,

                administrative_subdivision_id BIGINT UNSIGNED NULL,

                code VARCHAR(20) NOT NULL,

                type VARCHAR(30) NOT NULL DEFAULT 'standard',

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_postal_codes_country_code
                    (country_id, code),

                KEY idx_postal_codes_country
                    (country_id),

                KEY idx_postal_codes_division
                    (administrative_division_id),

                KEY idx_postal_codes_subdivision
                    (administrative_subdivision_id),

                CONSTRAINT fk_postal_codes_country
                    FOREIGN KEY (country_id)
                    REFERENCES countries(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_postal_codes_division
                    FOREIGN KEY (administrative_division_id)
                    REFERENCES administrative_divisions(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_postal_codes_subdivision
                    FOREIGN KEY (administrative_subdivision_id)
                    REFERENCES administrative_subdivisions(id)
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
            DROP TABLE IF EXISTS postal_codes
        ");
    }
}

return new CreatePostalCodesTable();