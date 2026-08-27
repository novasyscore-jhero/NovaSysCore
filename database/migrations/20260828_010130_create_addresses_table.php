<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateAddressesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                country_id BIGINT UNSIGNED NULL,

                administrative_division_id BIGINT UNSIGNED NULL,

                administrative_subdivision_id BIGINT UNSIGNED NULL,

                postal_code_id BIGINT UNSIGNED NULL,

                locality_id BIGINT UNSIGNED NULL,

                street VARCHAR(180) NULL,

                exterior_number VARCHAR(30) NULL,

                interior_number VARCHAR(30) NULL,

                neighborhood_text VARCHAR(180) NULL,

                city_text VARCHAR(150) NULL,

                reference VARCHAR(255) NULL,

                latitude DECIMAL(10,8) NULL,

                longitude DECIMAL(11,8) NULL,

                address_type VARCHAR(30) NOT NULL DEFAULT 'other',

                is_verified BOOLEAN NOT NULL DEFAULT FALSE,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                KEY idx_addresses_country
                    (country_id),

                KEY idx_addresses_division
                    (administrative_division_id),

                KEY idx_addresses_subdivision
                    (administrative_subdivision_id),

                KEY idx_addresses_postal_code
                    (postal_code_id),

                KEY idx_addresses_locality
                    (locality_id),

                KEY idx_addresses_type
                    (address_type),

                CONSTRAINT fk_addresses_country
                    FOREIGN KEY (country_id)
                    REFERENCES countries(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_addresses_division
                    FOREIGN KEY (administrative_division_id)
                    REFERENCES administrative_divisions(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_addresses_subdivision
                    FOREIGN KEY (administrative_subdivision_id)
                    REFERENCES administrative_subdivisions(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_addresses_postal_code
                    FOREIGN KEY (postal_code_id)
                    REFERENCES postal_codes(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_addresses_locality
                    FOREIGN KEY (locality_id)
                    REFERENCES localities(id)
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
            DROP TABLE IF EXISTS addresses
        ");
    }
}

return new CreateAddressesTable();