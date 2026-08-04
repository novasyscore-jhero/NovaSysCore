<?php

namespace NovaSysCore\Database;

use PDO;
use NovaSysCore\Database;

abstract class Migration
{
    protected PDO $database;

    public function __construct()
    {
        $this->database = Database::connection();
    }

    abstract public function up(): void;

    abstract public function down(): void;

    protected function execute(string $sql): void
    {
        $this->database->exec($sql);
    }
}