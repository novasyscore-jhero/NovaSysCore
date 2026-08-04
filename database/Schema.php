<?php

namespace NovaSysCore\Database;

use NovaSysCore\Database\Schema\Blueprint;
use NovaSysCore\Database;

class Schema
{

    public static function create(
        string $table,
        callable $callback
    )
    {

        $blueprint = new Blueprint($table);


        $callback($blueprint);


        $blueprint->create();

    }

    public static function drop(string $table): bool
    {

        $database = Database::connection();


        return $database->exec(
            "DROP TABLE IF EXISTS {$table}"
        ) !== false;

    }

}