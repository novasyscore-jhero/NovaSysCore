<?php

namespace NovaSysCore;

use PDO;

abstract class Model
{
    protected PDO $database;

    protected AuditLogger $audit;

    protected string $table;

    protected string $primaryKey = 'id';


    public function __construct()
    {
        $this->database = Database::connection();

        $this->audit = new AuditLogger();
    }


    public function find(int $id)
    {
        $query = "
            SELECT *
            FROM {$this->table}
            WHERE {$this->primaryKey} = :id
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


    public function create(array $data): int
    {
        $columns = array_keys($data);

        $placeholders = array_map(
            fn($column) => ':' . $column,
            $columns
        );

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->database->prepare($query);

        $stmt->execute($data);

        $id = (int)$this->database->lastInsertId();

        $this->audit->log(
            'CREATE',
            $this->table,
            $id,
            null,
            $data
        );

        return $id;
    }


    public function update(int $id, array $data): bool
    {
        $oldValues = $this->find($id);

        if (!$oldValues) {
            return false;
        }

        $sets = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = :{$column}";
        }

        $data['id'] = $id;

        $query = sprintf(
            "UPDATE %s SET %s WHERE %s = :id",
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );

        $stmt = $this->database->prepare($query);

        $result = $stmt->execute($data);

        if ($result) {

            $newValues = $this->find($id);

            $this->audit->log(
                'UPDATE',
                $this->table,
                $id,
                $oldValues,
                $newValues
            );
        }

        return $result;
    }


    public function delete(int $id): bool
    {
        $oldValues = $this->find($id);

        if (!$oldValues) {
            return false;
        }

        $query = "
            UPDATE {$this->table}
            SET deleted_at = NOW()
            WHERE {$this->primaryKey} = :id
        ";

        $stmt = $this->database->prepare($query);

        $result = $stmt->execute([
            'id' => $id
        ]);

        if ($result) {

            $this->audit->log(
                'DELETE',
                $this->table,
                $id,
                $oldValues,
                null
            );
        }

        return $result;
    }


    public function restore(int $id): bool
    {
        $query = "
            UPDATE {$this->table}
            SET deleted_at = NULL
            WHERE {$this->primaryKey} = :id
        ";

        $stmt = $this->database->prepare($query);

        $result = $stmt->execute([
            'id' => $id
        ]);

        if ($result) {

            $newValues = $this->find($id);

            $this->audit->log(
                'RESTORE',
                $this->table,
                $id,
                null,
                $newValues
            );
        }

        return $result;
    }
}