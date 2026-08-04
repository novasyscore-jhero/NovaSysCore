<?php

namespace NovaSysCore;

use PDO;

abstract class Model
{
    protected PDO $database;

    protected string $table;


    public function __construct()
    {
        $this->database = Database::connection();
    }


    public function find(int $id)
    {
        $query = "
            SELECT *
            FROM {$this->table}
            WHERE id = :id
            AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->database->prepare($query);

        $stmt->execute([
            'id' => $id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    public function all()
    {
        $query = "
            SELECT *
            FROM {$this->table}
            WHERE deleted_at IS NULL
        ";

        return $this->database
            ->query($query)
            ->fetchAll(PDO::FETCH_ASSOC);
    }


    public function delete(int $id)
    {
        $query = "
            UPDATE {$this->table}
            SET deleted_at = NOW()
            WHERE id = :id
        ";


        $stmt = $this->database->prepare($query);


        return $stmt->execute([
            'id'=>$id
        ]);
    }


    public function restore(int $id)
    {
        $query = "
            UPDATE {$this->table}
            SET deleted_at = NULL
            WHERE id = :id
        ";


        $stmt = $this->database->prepare($query);


        return $stmt->execute([
            'id'=>$id
        ]);
    }
}