<?php

namespace NovaSysCore\Database\Seeders;

use NovaSysCore\Database\Seeders\Geography\CountriesSeeder;
use NovaSysCore\Database\Seeders\Security\PermissionsSeeder;
use NovaSysCore\Database\Seeders\Security\RolesSeeder;
use NovaSysCore\Database\Seeders\Security\RolePermissionsSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        (new CountriesSeeder())->run();
        (new PermissionsSeeder())->run();
        (new RolesSeeder())->run();
        (new RolePermissionsSeeder())->run();
    }
}