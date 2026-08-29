<?php

namespace NovaSysCore\Console\Commands;

use NovaSysCore\Console\Command;
use NovaSysCore\Database\Seeders\DatabaseSeeder;
use NovaSysCore\Database\Seeders\Seeder;
use RuntimeException;

class DbSeedCommand extends Command
{
    protected string $name = 'db:seed';

    protected string $description =
        'Ejecuta todos los seeders o un seeder específico';

    public function execute(array $arguments = []): int
    {
        $requestedSeeder = $arguments[0] ?? null;

        /*
         * Sin argumentos:
         *
         * php novasys db:seed
         *
         * Ejecuta el DatabaseSeeder tradicional.
         */
        if ($requestedSeeder === null) {
            $seeder = new DatabaseSeeder();

            $seeder->run();

            echo "Seeders ejecutados correctamente 🚀" . PHP_EOL;

            return 0;
        }

        /*
         * Con argumento:
         *
         * php novasys db:seed AuthorizationTestSeeder
         */
        $seederClass = $this->resolveSeederClass(
            $requestedSeeder
        );

        if ($seederClass === null) {
            throw new RuntimeException(
                "No se encontró el seeder: {$requestedSeeder}"
            );
        }

        if (!is_subclass_of($seederClass, Seeder::class)) {
            throw new RuntimeException(
                "La clase {$seederClass} no es un Seeder válido."
            );
        }

        $seeder = new $seederClass();

        $seeder->run();

        echo PHP_EOL;
        echo "Seeder {$requestedSeeder} ejecutado correctamente 🚀"
            . PHP_EOL;

        return 0;
    }

    /**
     * Busca un Seeder por su nombre corto.
     *
     * Ejemplos:
     *
     * AuthorizationTestSeeder
     * PermissionsSeeder
     * RolesSeeder
     * CountriesSeeder
     */
    private function resolveSeederClass(
        string $seederName
    ): ?string {
        /*
         * Si recibimos un nombre de clase completo,
         * intentamos utilizarlo directamente.
         */
        if (str_contains($seederName, '\\')) {
            if (class_exists($seederName)) {
                return $seederName;
            }

            return null;
        }

        /*
         * Directorios/namespaces donde actualmente
         * NovaSysCore almacena seeders.
         *
         * Podemos agregar nuevos namespaces aquí
         * cuando aparezcan nuevos módulos.
         */
        $namespaces = [
            'NovaSysCore\\Database\\Seeders\\',
            'NovaSysCore\\Database\\Seeders\\Security\\',
            'NovaSysCore\\Database\\Seeders\\Geography\\',
            'NovaSysCore\\Database\\Seeders\\Development\\',
        ];

        foreach ($namespaces as $namespace) {
            $class = $namespace . $seederName;

            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}