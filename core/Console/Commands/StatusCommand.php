<?php

namespace NovaSysCore\Console\Commands;

use NovaSysCore\Console\Command;
use NovaSysCore\Database\Migrator;

class StatusCommand extends Command
{
    protected string $name = 'status';

    protected string $description = 'Muestra el estado de las migraciones';

    public function execute(array $arguments = []): int
    {
        $migrator = new Migrator();

        $executed = $migrator->getExecutedMigrations();

        $files = glob(
            dirname(__DIR__, 3) . '/database/migrations/*.php'
        );

        sort($files);

        echo PHP_EOL;
        echo "Estado de migraciones" . PHP_EOL;
        echo str_repeat('-', 50) . PHP_EOL;

        foreach ($files as $file) {

            $migration = basename($file);

            if (in_array($migration, $executed)) {

                echo "[OK]      {$migration}" . PHP_EOL;

            } else {

                echo "[PEND]    {$migration}" . PHP_EOL;

            }

        }

        echo str_repeat('-', 50) . PHP_EOL;

        return 0;
    }
}