<?php

namespace NovaSysCore\Console\Commands;

use NovaSysCore\Console\Command;
use NovaSysCore\Database\Migrator;

class MigrateCommand extends Command
{
    protected string $name = 'migrate';

    protected string $description = 'Ejecuta las migraciones pendientes';

    public function execute(array $arguments = []): int
    {
        $migrator = new Migrator();

        $migrator->run();

        echo "Migraciones ejecutadas correctamente 🚀" . PHP_EOL;

        return 0;
    }
}