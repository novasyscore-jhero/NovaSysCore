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

    /*
     * =========================================================
     * AUDITORÍA DE CAMBIOS DE REGISTROS
     * =========================================================
     *
     * Conservamos este método para no romper código existente.
     */
    public function log(
        string $action,
        string $table,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): bool {
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
            'old_values' => $this->encodeJson(
                $oldValues
            ),
            'new_values' => $this->encodeJson(
                $newValues
            ),
        ]);
    }

    /*
     * =========================================================
     * AUDITORÍA DE EVENTOS DE SEGURIDAD
     * =========================================================
     */
    public function security(
        string $action,
        ?int $userId = null,
        ?array $metadata = null,
        ?int $companyId = null,
        ?int $branchId = null
    ): bool {
        $query = "
            INSERT INTO audit_logs
            (
                user_id,
                action,
                company_id,
                branch_id,
                ip_address,
                user_agent_hash,
                metadata,
                created_at
            )
            VALUES
            (
                :user_id,
                :action,
                :company_id,
                :branch_id,
                :ip_address,
                :user_agent_hash,
                :metadata,
                NOW()
            )
        ";

        $stmt = $this->database->prepare($query);

        return $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'ip_address' => $this->getClientIpAddress(),
            'user_agent_hash' => $this->getUserAgentHash(),
            'metadata' => $this->encodeJson(
                $metadata
            ),
        ]);
    }

    private function encodeJson(
        ?array $values
    ): ?string {
        if ($values === null) {
            return null;
        }

        $json = json_encode(
            $values,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException(
                'No fue posible serializar los datos de auditoría.'
            );
        }

        return $json;
    }

    private function getClientIpAddress(): ?string
    {
        $ipAddress = $_SERVER['REMOTE_ADDR']
            ?? null;

        if (
            !is_string($ipAddress)
            || $ipAddress === ''
        ) {
            return null;
        }

        return substr(
            $ipAddress,
            0,
            45
        );
    }

    private function getUserAgentHash(): ?string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT']
            ?? null;

        if (
            !is_string($userAgent)
            || $userAgent === ''
        ) {
            return null;
        }

        return hash(
            'sha256',
            $userAgent
        );
    }
}