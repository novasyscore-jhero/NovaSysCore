<?php

namespace NovaSysCore\Database;

use PDO;
use NovaSysCore\Database;
use NovaSysCore\Database\Migration;
use NovaSysCore\Exceptions\MigrationException;

class Migrator
{
    protected PDO $database;

    public function __construct()
    {
        $this->database = Database::connection();
    }

    public function ensureMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $this->database->exec($sql);
    }

    public function getExecutedMigrations(): array
    {
        $this->ensureMigrationsTable();

        $stmt = $this->database->query(
            "SELECT migration FROM migrations"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function markAsExecuted(string $migration): void
    {
        $stmt = $this->database->prepare(
            "INSERT INTO migrations (migration) VALUES (:migration)"
        );

        $stmt->execute([
            'migration' => $migration
        ]);
    }

    public function run(): void
    {
        $this->ensureMigrationsTable();

        $executed = $this->getExecutedMigrations();

        $files = glob(
            dirname(__DIR__) . '/database/migrations/*.php'
        );

        sort($files);

        foreach ($files as $file) {

            $migrationName = basename($file);

            if (in_array($migrationName, $executed)) {
                continue;
            }

            $migration = require $file;

            if (!$migration instanceof Migration) {

                throw new \Exception(
                    "La migración {$migrationName} no es válida"
                );

            }

            try {

                $this->database->beginTransaction();
            
                $migration->up();
            
                $this->markAsExecuted(
                    $migrationName
                );
            
                $this->database->commit();
            
                echo "[OK] {$migrationName}\n";
            
            } catch (\Throwable $e) {
            
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                }
            
                throw new MigrationException(
                    "Error ejecutando {$migrationName}: "
                    . $e->getMessage(),
                    0,
                    $e
                );
            }

        }
    }

    public function getLastMigration(): ?string
    {
        $stmt = $this->database->query(
            "SELECT migration 
            FROM migrations 
            ORDER BY id DESC 
            LIMIT 1"
        );

        $result = $stmt->fetchColumn();

        return $result ?: null;
    }

    public function removeMigration(string $migration): void
    {
        $stmt = $this->database->prepare(
            "DELETE FROM migrations 
            WHERE migration = :migration"
        );

        $stmt->execute([
            'migration' => $migration
        ]);
    }

    public function rollback(): void
    {
        $migrationName = $this->getLastMigration();

        if (!$migrationName) {
            echo "No hay migraciones para revertir.\n";
            return;
        }

        $file = dirname(__DIR__)
            . '/database/migrations/'
            . $migrationName;

        if (!file_exists($file)) {

            throw new \Exception(
                "No existe el archivo de migración: {$migrationName}"
            );

        }

        $migration = require $file;

        if (!$migration instanceof Migration) {

            throw new \Exception(
                "Migración inválida: {$migrationName}"
            );

        }

        try {

            $this->database->beginTransaction();
        
            $migration->down();
        
            $this->removeMigration(
                $migrationName
            );
        
            $this->database->commit();
        
            echo "[ROLLBACK] {$migrationName}\n";
        
        } catch (\Throwable $e) {
        
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
        
            throw new MigrationException(
                "Error revirtiendo {$migrationName}: "
                . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
