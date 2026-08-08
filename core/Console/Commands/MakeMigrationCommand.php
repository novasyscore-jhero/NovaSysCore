<?php

namespace NovaSysCore\Console\Commands;

use NovaSysCore\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';

    protected string $description = 'Crea una nueva migración';

    public function execute(array $arguments = []): int
    {
        $name = $arguments[0] ?? null;

        if ($name === null) {
            echo "Error: debes indicar el nombre de la migración." . PHP_EOL;
            echo "Ejemplo: php novasys make:migration create_users_table" . PHP_EOL;

            return 1;
        }

        $timestamp = date('Ymd_His');

        $filename = $timestamp . '_' . $name . '.php';

        $directory = dirname(__DIR__, 3) . '/database/migrations';

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $className = str_replace(
            ' ',
            '',
            ucwords(str_replace('_', ' ', $name))
        );

        $content = "<?php\n\n"
            . "namespace NovaSysCore\\Database\\Migrations;\n\n"
            . "use NovaSysCore\\Database\\Migration;\n\n"
            . "class {$className} extends Migration\n"
            . "{\n"
            . "    public function up(): void\n"
            . "    {\n"
            . "        //\n"
            . "    }\n\n"
            . "    public function down(): void\n"
            . "    {\n"
            . "        //\n"
            . "    }\n"
            . "}\n\n"
            . "return new {$className}();\n";

        $path = $directory . '/' . $filename;

        if (file_exists($path)) {
            echo "Error: la migración ya existe." . PHP_EOL;

            return 1;
        }

        file_put_contents($path, $content);

        echo "[OK] Migración creada: {$filename}" . PHP_EOL;

        return 0;
    }
}
