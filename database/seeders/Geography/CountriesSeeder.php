<?php

namespace NovaSysCore\Database\Seeders\Geography;

use NovaSysCore\Database\Seeders\Seeder;
use NovaSysCore\Database;

class CountriesSeeder extends Seeder
{
    public function run(): void
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare("
            INSERT INTO countries (
                name,
                official_name,
                iso2,
                iso3,
                numeric_code,
                phone_code,
                currency,
                locale,
                timezone,
                status
            )
            VALUES (
                :name,
                :official_name,
                :iso2,
                :iso3,
                :numeric_code,
                :phone_code,
                :currency,
                :locale,
                :timezone,
                :status
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                official_name = VALUES(official_name),
                phone_code = VALUES(phone_code),
                currency = VALUES(currency),
                locale = VALUES(locale),
                timezone = VALUES(timezone),
                status = VALUES(status)
        ");

        $statement->execute([
            'name' => 'México',
            'official_name' => 'Estados Unidos Mexicanos',
            'iso2' => 'MX',
            'iso3' => 'MEX',
            'numeric_code' => '484',
            'phone_code' => '+52',
            'currency' => 'MXN',
            'locale' => 'es-MX',
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
        ]);

        echo "[OK] Países base cargados" . PHP_EOL;
    }
}