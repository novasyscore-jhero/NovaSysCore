<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateLocalitiesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE localities (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                postal_code_id BIGINT UNSIGNED NOT NULL,

                administrative_subdivision_id BIGINT UNSIGNED NULL,

                name VARCHAR(180) NOT NULL,

                type VARCHAR(50) NOT NULL,

                slug VARCHAR(200) NOT NULL,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_localities_postal_slug
                    (postal_code_id, slug),

                KEY idx_localities_postal_code
                    (postal_code_id),

                KEY idx_localities_subdivision
                    (administrative_subdivision_id),

                CONSTRAINT fk_localities_postal_code
                    FOREIGN KEY (postal_code_id)
                    REFERENCES postal_codes(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_localities_subdivision
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
            DROP TABLE IF EXISTS localities
        ");
    }
}

return new CreateLocalitiesTable();