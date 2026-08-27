<?php

namespace NovaSysCore\Console\Commands;

use NovaSysCore\Console\Command;
use NovaSysCore\Database\Seeders\DatabaseSeeder;

class DbSeedCommand extends Command
{
    protected string $name = 'db:seed';

    protected string $description = 'Ejecuta los seeders de la base de datos';

    public function execute(array $arguments = []): int
    {
        $seeder = new DatabaseSeeder();

        $seeder->run();

        echo "Seeders ejecutados correctamente 🚀" . PHP_EOL;

        return 0;
    }
}