<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateAdministrativeSubdivisionsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE administrative_subdivisions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                administrative_division_id BIGINT UNSIGNED NOT NULL,

                name VARCHAR(150) NOT NULL,

                code VARCHAR(30) NULL,

                type VARCHAR(50) NOT NULL,

                slug VARCHAR(180) NOT NULL,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_admin_subdivisions_division_code
                    (administrative_division_id, code),

                UNIQUE KEY uq_admin_subdivisions_division_slug
                    (administrative_division_id, slug),

                KEY idx_admin_subdivisions_division
                    (administrative_division_id),

                CONSTRAINT fk_admin_subdivisions_division
                    FOREIGN KEY (administrative_division_id)
                    REFERENCES administrative_divisions(id)
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
            DROP TABLE IF EXISTS administrative_subdivisions
        ");
    }
}

return new CreateAdministrativeSubdivisionsTable();