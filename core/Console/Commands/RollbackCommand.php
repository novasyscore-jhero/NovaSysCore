<?php

namespace NovaSysCore\Console\Commands;

use NovaSysCore\Console\Command;
use NovaSysCore\Database\Migrator;

class RollbackCommand extends Command
{
    protected string $name = 'rollback';

    protected string $description = 'Revierte la última migración ejecutada';

    public function execute(array $arguments = []): int
    {
        $migrator = new Migrator();

        $migrator->rollback();

        echo "Rollback ejecutado correctamente 🚀" . PHP_EOL;

        return 0;
    }
}