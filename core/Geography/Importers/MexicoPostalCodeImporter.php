<?php

namespace NovaSysCore\Geography\Importers;

use NovaSysCore\Database;
use PDO;
use RuntimeException;

class MexicoPostalCodeImporter
{
    private PDO $pdo;

    private int $countryId;

    private array $divisions = [];

    private array $subdivisions = [];

    private array $postalCodes = [];

    private int $postalCodesProcessed = 0;

    private int $localitiesProcessed = 0;

    private int $rows = 0;

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

        $this->pdo = Database::connection();

        $this->countryId = $this->getMexicoCountryId();

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

        echo PHP_EOL;
        echo "[OK] Catálogo SEPOMEX procesado" . PHP_EOL;
        echo "Registros procesados: {$this->rows}" . PHP_EOL;
        echo "Entidades detectadas: "
            . count($this->divisions)
            . PHP_EOL;
        echo "Municipios/alcaldías detectados: "
            . count($this->subdivisions)
            . PHP_EOL;
        echo "Códigos postales nuevos: "
            . $this->postalCodesProcessed
            . PHP_EOL;
        
        echo "Localidades procesadas: "
            . $this->localitiesProcessed
            . PHP_EOL;
    }

    private function getMexicoCountryId(): int
    {
        $statement = $this->pdo->prepare("
            SELECT id
            FROM countries
            WHERE iso2 = :iso2
            LIMIT 1
        ");

        $statement->execute([
            'iso2' => 'MX',
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                "México no existe en countries. "
                . "Ejecuta primero: php novasys db:seed"
            );
        }

        return (int) $id;
    }

    private function process($handle): void
    {
        /*
         * Primera línea:
         * aviso legal de Correos de México.
         */
        if (fgets($handle) === false) {
            throw new RuntimeException(
                "El archivo SEPOMEX está vacío."
            );
        }

        /*
         * Segunda línea:
         * encabezados.
         */
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

        while (($line = fgets($handle)) !== false) {

            $line = trim($line);


            if ($line === '') {
                continue;
            }

            $line = mb_convert_encoding(
                $line,
                'UTF-8',
                'Windows-1252'
            );

            $headerLine = mb_convert_encoding(
                $headerLine,
                'UTF-8',
                'Windows-1252'
            );

            $values = str_getcsv($line, '|');

            if (count($values) !== count($headers)) {
                throw new RuntimeException(
                    "Fila inválida cerca del registro "
                    . ($this->rows + 1)
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

            $divisionId = $this->findOrCreateDivision(
                trim($row['c_estado']),
                trim($row['d_estado'])
            );

            $subdivisionId = $this->findOrCreateSubdivision(
                $divisionId,
                trim($row['c_mnpio']),
                trim($row['D_mnpio'])
            );

            $postalCodeId = $this->findOrCreatePostalCode(
                $divisionId,
                $subdivisionId,
                trim($row['d_codigo'])
            );

            $this->upsertLocality(
                $postalCodeId,
                $subdivisionId,
                trim($row['d_asenta']),
                trim($row['d_tipo_asenta']),
                trim($row['id_asenta_cpcons'])
            );

            $this->rows++;

            if ($this->rows % 5000 === 0) {
                echo "Procesados: "
                    . number_format($this->rows)
                    . " registros..."
                    . PHP_EOL;
            }
        }
    }

    private function findOrCreateDivision(
        string $code,
        string $name
    ): int {
        $cacheKey = $code;

        if (isset($this->divisions[$cacheKey])) {
            return $this->divisions[$cacheKey];
        }

        $statement = $this->pdo->prepare("
            SELECT id
            FROM administrative_divisions
            WHERE country_id = :country_id
              AND code = :code
            LIMIT 1
        ");

        $statement->execute([
            'country_id' => $this->countryId,
            'code' => $code,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {

            $statement = $this->pdo->prepare("
                INSERT INTO administrative_divisions (
                    country_id,
                    name,
                    code,
                    type,
                    slug,
                    status
                )
                VALUES (
                    :country_id,
                    :name,
                    :code,
                    :type,
                    :slug,
                    'active'
                )
            ");

            $statement->execute([
                'country_id' => $this->countryId,
                'name' => $name,
                'code' => $code,
                'type' => 'state',
                'slug' => $this->slug($name),
            ]);

            $id = $this->pdo->lastInsertId();
        }

        $this->divisions[$cacheKey] = (int) $id;

        return (int) $id;
    }

    private function findOrCreateSubdivision(
        int $divisionId,
        string $code,
        string $name
    ): int {
        /*
         * c_mnpio se repite entre diferentes estados.
         * Por eso la clave debe contener también divisionId.
         */
        $cacheKey = $divisionId . ':' . $code;

        if (isset($this->subdivisions[$cacheKey])) {
            return $this->subdivisions[$cacheKey];
        }

        $statement = $this->pdo->prepare("
            SELECT id
            FROM administrative_subdivisions
            WHERE administrative_division_id = :division_id
              AND code = :code
            LIMIT 1
        ");

        $statement->execute([
            'division_id' => $divisionId,
            'code' => $code,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {

            $statement = $this->pdo->prepare("
                INSERT INTO administrative_subdivisions (
                    administrative_division_id,
                    name,
                    code,
                    type,
                    slug,
                    status
                )
                VALUES (
                    :division_id,
                    :name,
                    :code,
                    :type,
                    :slug,
                    'active'
                )
            ");

            $statement->execute([
                'division_id' => $divisionId,
                'name' => $name,
                'code' => $code,
                'type' => 'municipality',
                'slug' => $this->slug($name),
            ]);

            $id = $this->pdo->lastInsertId();
        }

        $this->subdivisions[$cacheKey] = (int) $id;

        return (int) $id;
    }

    private function findOrCreatePostalCode(
        int $divisionId,
        int $subdivisionId,
        string $code
    ): int {
        $cacheKey = $this->countryId . ':' . $code;

        if (isset($this->postalCodes[$cacheKey])) {
            return $this->postalCodes[$cacheKey];
        }

        $statement = $this->pdo->prepare("
            SELECT id
            FROM postal_codes
            WHERE country_id = :country_id
              AND code = :code
            LIMIT 1
        ");

        $statement->execute([
            'country_id' => $this->countryId,
            'code' => $code,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {

            $statement = $this->pdo->prepare("
                INSERT INTO postal_codes (
                    country_id,
                    administrative_division_id,
                    administrative_subdivision_id,
                    code,
                    type,
                    status
                )
                VALUES (
                    :country_id,
                    :division_id,
                    :subdivision_id,
                    :code,
                    'standard',
                    'active'
                )
            ");

            $statement->execute([
                'country_id' => $this->countryId,
                'division_id' => $divisionId,
                'subdivision_id' => $subdivisionId,
                'code' => $code,
            ]);

            $id = $this->pdo->lastInsertId();

            $this->postalCodesProcessed++;
        }

        $this->postalCodes[$cacheKey] = (int) $id;

        return (int) $id;
    }

    private function upsertLocality(
        int $postalCodeId,
        int $subdivisionId,
        string $name,
        string $type,
        string $sourceCode
    ): void {
        $slug = $this->slug($name);

        $statement = $this->pdo->prepare("
            INSERT INTO localities (
                postal_code_id,
                administrative_subdivision_id,
                name,
                type,
                slug,
                source_code,
                status
            )
            VALUES (
                :postal_code_id,
                :subdivision_id,
                :name,
                :type,
                :slug,
                :source_code,
                'active'
            )
            ON DUPLICATE KEY UPDATE
                administrative_subdivision_id =
                    VALUES(administrative_subdivision_id),
                name = VALUES(name),
                type = VALUES(type),
                source_code = VALUES(source_code),
                status = 'active'
        ");

        $statement->execute([
            'postal_code_id' => $postalCodeId,
            'subdivision_id' => $subdivisionId,
            'name' => $name,
            'type' => $type,
            'slug' => $slug,
            'source_code' => $sourceCode,
        ]);

        $this->localitiesProcessed++;
    }

    private function slug(string $value): string
    {
        $value = trim($value);

        $characters = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ];

        $value = strtr(
            $value,
            $characters
        );

        $value = strtolower($value);

        $value = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        );

        return trim(
            $value ?? '',
            '-'
        );
    }
}