<?php

namespace NovaSysCore\Geography\Importers;

use RuntimeException;

class MexicoPostalCodeImporter
{
    public function import(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException(
                "No se encontró el archivo: {$filePath}"
            );
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(
                "No se puede leer el archivo: {$filePath}"
            );
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                "No fue posible abrir el archivo."
            );
        }

        try {
            $this->process($handle);
        } finally {
            fclose($handle);
        }
    }

    private function process($handle): void
    {
        $legalNotice = fgets($handle);

        if ($legalNotice === false) {
            throw new RuntimeException(
                "El archivo está vacío."
            );
        }

        $headerLine = fgets($handle);

        if ($headerLine === false) {
            throw new RuntimeException(
                "No se encontró el encabezado SEPOMEX."
            );
        }

        $headers = str_getcsv(
            trim($headerLine),
            '|'
        );

        $requiredColumns = [
            'd_codigo',
            'd_asenta',
            'd_tipo_asenta',
            'D_mnpio',
            'd_estado',
            'c_estado',
            'c_mnpio',
            'id_asenta_cpcons',
        ];

        foreach ($requiredColumns as $column) {
            if (!in_array($column, $headers, true)) {
                throw new RuntimeException(
                    "Falta la columna requerida: {$column}"
                );
            }
        }

        $rows = 0;

        while (($line = fgets($handle)) !== false) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line, '|');

            if (count($values) !== count($headers)) {
                throw new RuntimeException(
                    "Fila inválida cerca del registro "
                    . ($rows + 1)
                );
            }

            $row = array_combine(
                $headers,
                $values
            );

            if ($row === false) {
                throw new RuntimeException(
                    "No fue posible interpretar una fila."
                );
            }

            $rows++;
        }

        echo "[OK] Archivo SEPOMEX válido" . PHP_EOL;
        echo "Registros detectados: {$rows}" . PHP_EOL;
    }
}