<?php

namespace NovaSysCore\Database\Seeders;

use NovaSysCore\Database\Seeders\Geography\CountriesSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        (new CountriesSeeder())->run();
    }
}