<?php

namespace NovaSysCore\Console;

use NovaSysCore\Console\Commands\MigrateCommand;
use NovaSysCore\Console\Commands\RollbackCommand;
use NovaSysCore\Console\Commands\StatusCommand;
use NovaSysCore\Console\Commands\MakeMigrationCommand;

class Kernel
{
    protected array $commands = [];

    public function __construct()
    {
        $this->register(new MigrateCommand());
        $this->register(new RollbackCommand());
        $this->register(new StatusCommand());
        $this->register(new MakeMigrationCommand());
    }

    public function register(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function handle(array $argv): int
    {
        $commandName = $argv[1] ?? null;

        if ($commandName === null) {
            $this->showHelp();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {

            echo "Comando no encontrado: {$commandName}" . PHP_EOL;

            $this->showHelp();

            return 1;

        }

        $arguments = array_slice($argv, 2);

        return $this->commands[$commandName]
            ->execute($arguments);
    }

    protected function showHelp(): void
    {
        echo "NovaSysCore Console" . PHP_EOL;

        echo "Comandos disponibles:" . PHP_EOL;

        foreach ($this->commands as $command) {

            echo "  "
                . $command->getName()
                . " - "
                . $command->getDescription()
                . PHP_EOL;

        }
    }
}