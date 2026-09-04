<?php

namespace NovaSysCore\Security;

use NovaSysCore\Config;
use NovaSysCore\Database;
use PDO;

class LoginRateLimiter
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function isBlocked(
        string $identifier,
        string $ipAddress
    ): bool {
        $identifierHash = $this->hashIdentifier(
            $identifier
        );

        $config = Config::get(
            'security.login_rate_limit'
        );

        $windowMinutes = (int) (
            $config['window_minutes'] ?? 15
        );

        $pairMaxFailures = (int) (
            $config['pair_max_failures'] ?? 5
        );

        $ipMaxFailures = (int) (
            $config['ip_max_failures'] ?? 20
        );

        $pairFailures = $this->countPairFailures(
            $identifierHash,
            $ipAddress,
            $windowMinutes
        );

        if ($pairFailures >= $pairMaxFailures) {
            return true;
        }

        $ipFailures = $this->countIpFailures(
            $ipAddress,
            $windowMinutes
        );

        return $ipFailures >= $ipMaxFailures;
    }

    public function recordFailure(
        string $identifier,
        string $ipAddress,
        ?int $userId = null,
        string $reason = 'invalid_credentials'
    ): void {
        $this->insertAttempt(
            $identifier,
            $ipAddress,
            $userId,
            false,
            false,
            $reason
        );
    }

    public function recordBlocked(
        string $identifier,
        string $ipAddress,
        ?int $userId = null
    ): void {
        $this->insertAttempt(
            $identifier,
            $ipAddress,
            $userId,
            false,
            true,
            'rate_limited'
        );
    }

    public function recordSuccess(
        string $identifier,
        string $ipAddress,
        int $userId
    ): void {
        $this->insertAttempt(
            $identifier,
            $ipAddress,
            $userId,
            true,
            false,
            null
        );
    }

    private function countPairFailures(
        string $identifierHash,
        string $ipAddress,
        int $windowMinutes
    ): int {
        $statement = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM login_attempts
            WHERE identifier_hash = :identifier_hash
              AND ip_address = :ip_address
              AND was_successful = 0
              AND was_blocked = 0
              AND attempted_at >= DATE_SUB(
                    NOW(),
                    INTERVAL :window_minutes MINUTE
              )
        ");

        $statement->bindValue(
            ':identifier_hash',
            $identifierHash
        );

        $statement->bindValue(
            ':ip_address',
            $ipAddress
        );

        $statement->bindValue(
            ':window_minutes',
            $windowMinutes,
            PDO::PARAM_INT
        );

        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function countIpFailures(
        string $ipAddress,
        int $windowMinutes
    ): int {
        $statement = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM login_attempts
            WHERE ip_address = :ip_address
              AND was_successful = 0
              AND was_blocked = 0
              AND attempted_at >= DATE_SUB(
                    NOW(),
                    INTERVAL :window_minutes MINUTE
              )
        ");

        $statement->bindValue(
            ':ip_address',
            $ipAddress
        );

        $statement->bindValue(
            ':window_minutes',
            $windowMinutes,
            PDO::PARAM_INT
        );

        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function insertAttempt(
        string $identifier,
        string $ipAddress,
        ?int $userId,
        bool $successful,
        bool $blocked,
        ?string $reason
    ): void {
        $statement = $this->pdo->prepare("
            INSERT INTO login_attempts (
                user_id,
                identifier_hash,
                ip_address,
                user_agent_hash,
                was_successful,
                was_blocked,
                failure_reason,
                attempted_at
            )
            VALUES (
                :user_id,
                :identifier_hash,
                :ip_address,
                :user_agent_hash,
                :was_successful,
                :was_blocked,
                :failure_reason,
                NOW()
            )
        ");

        $statement->bindValue(
            ':user_id',
            $userId,
            $userId === null
                ? PDO::PARAM_NULL
                : PDO::PARAM_INT
        );

        $statement->bindValue(
            ':identifier_hash',
            $this->hashIdentifier(
                $identifier
            )
        );

        $statement->bindValue(
            ':ip_address',
            $ipAddress
        );

        $statement->bindValue(
            ':user_agent_hash',
            $this->getUserAgentHash(),
            $this->getUserAgentHash() === null
                ? PDO::PARAM_NULL
                : PDO::PARAM_STR
        );

        $statement->bindValue(
            ':was_successful',
            $successful ? 1 : 0,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':was_blocked',
            $blocked ? 1 : 0,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':failure_reason',
            $reason,
            $reason === null
                ? PDO::PARAM_NULL
                : PDO::PARAM_STR
        );

        $statement->execute();
    }

    private function hashIdentifier(
        string $identifier
    ): string {
        return hash(
            'sha256',
            strtolower(
                trim($identifier)
            )
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