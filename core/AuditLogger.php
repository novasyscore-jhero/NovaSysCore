<?php

namespace NovaSysCore;

use PDO;

class AuditLogger
{

    protected PDO $database;


    public function __construct()
    {
        $this->database = Database::connection();
    }


    public function log(
        string $action,
        string $table,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): bool
    {

        $query = "
            INSERT INTO audit_logs
            (
                action,
                table_name,
                record_id,
                old_values,
                new_values,
                created_at
            )
            VALUES
            (
                :action,
                :table_name,
                :record_id,
                :old_values,
                :new_values,
                NOW()
            )
        ";


        $stmt = $this->database->prepare($query);


        return $stmt->execute([

            'action' => $action,

            'table_name' => $table,

            'record_id' => $recordId,

            'old_values' => $oldValues 
                ? json_encode($oldValues)
                : null,

            'new_values' => $newValues
                ? json_encode($newValues)
                : null

        ]);

    }

}