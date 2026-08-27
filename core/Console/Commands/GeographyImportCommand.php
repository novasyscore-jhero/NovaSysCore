<?php

namespace NovaSysCore\Console\Commands;

use NovaSysCore\Console\Command;
use NovaSysCore\Geography\Importers\MexicoPostalCodeImporter;

class GeographyImportCommand extends Command
{
    protected string $name = 'geography:import';

    protected string $description = 'Importa un catálogo geográfico';

    public function execute(array $arguments = []): int
    {
        $country = $arguments[0] ?? null;
        $filePath = $arguments[1] ?? null;

        if ($country === null || $filePath === null) {
            echo "Error: debes indicar el país y la ruta del archivo."
                . PHP_EOL;

            echo 'Ejemplo: php novasys geography:import mexico "C:\ruta\archivo.txt"'
                . PHP_EOL;

            return 1;
        }

        try {

            switch (strtolower($country)) {

                case 'mexico':
                case 'méxico':

                    $importer = new MexicoPostalCodeImporter();
                    $importer->import($filePath);

                    break;

                default:

                    echo "Error: importador no disponible para {$country}."
                        . PHP_EOL;

                    return 1;
            }

        } catch (\Throwable $e) {

            echo "Error de importación: "
                . $e->getMessage()
                . PHP_EOL;

            return 1;
        }

        return 0;
    }
}