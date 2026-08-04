<?php

namespace NovaSysCore\Database\Schema;


use NovaSysCore\Database;
use PDO;


class Blueprint
{

    protected string $table;

    protected array $columns = [];


    public function __construct(string $table)
    {
        $this->table = $table;
    }



    public function id(): self
    {

        $this->columns[] =
            "id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";


        return $this;

    }



    public function string(
        string $name,
        int $length = 255
    ): self
    {

        $this->columns[] =
            "{$name} VARCHAR({$length})";


        return $this;

    }



    public function integer(
        string $name
    ): self
    {

        $this->columns[] =
            "{$name} INT";


        return $this;

    }



    public function decimal(
        string $name,
        int $total = 10,
        int $decimal = 2
    ): self
    {

        $this->columns[] =
            "{$name} DECIMAL({$total},{$decimal})";


        return $this;

    }



    public function timestamps(): self
    {

        $this->columns[] =
            "created_at DATETIME";

        $this->columns[] =
            "updated_at DATETIME";


        return $this;

    }



    public function create(): bool
    {

        $sql = "
            CREATE TABLE {$this->table}
            (
                " . implode(
                    ",",
                    $this->columns
                ) . "
            )
        ";


        $database = Database::connection();


        return $database
            ->exec($sql) !== false;

    }

}