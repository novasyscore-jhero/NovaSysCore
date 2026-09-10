<?php

namespace NovaSysCore\Context;

use NovaSysCore\Database;
use PDO;

class CompanyContextSelector
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function availableCompanies(
        int $userId
    ): array {
        if ($userId <= 0) {
            return [];
        }

        if (!$this->isUserActive($userId)) {
            return [];
        }

        if ($this->isSuperAdministrator($userId)) {
            return $this->allActiveCompanies();
        }

        return $this->userActiveCompanies(
            $userId
        );
    }

    private function userActiveCompanies(
        int $userId
    ): array {
        $statement = $this->pdo->prepare("
            SELECT
                c.id,
                c.name,
                c.slug
            FROM user_companies uc

            INNER JOIN companies c
                ON c.id = uc.company_id

            WHERE uc.user_id = :user_id
              AND uc.status = 'active'
              AND c.status = 'active'

            ORDER BY
                c.name ASC,
                c.id ASC
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);

        return $statement->fetchAll();
    }

    private function allActiveCompanies(): array
    {
        $statement = $this->pdo->query("
            SELECT
                id,
                name,
                slug
            FROM companies

            WHERE status = 'active'

            ORDER BY
                name ASC,
                id ASC
        ");

        return $statement->fetchAll();
    }

    private function isUserActive(
        int $userId
    ): bool {
        $statement = $this->pdo->prepare("
            SELECT 1
            FROM users
            WHERE id = :user_id
              AND status = 'active'
            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);

        return $statement->fetchColumn()
            !== false;
    }

    private function isSuperAdministrator(
        int $userId
    ): bool {
        $statement = $this->pdo->prepare("
            SELECT 1
            FROM user_system_roles usr

            INNER JOIN roles r
                ON r.id = usr.role_id

            WHERE usr.user_id = :user_id
              AND r.company_id IS NULL
              AND r.slug = 'super-administrator'
              AND r.is_system = TRUE
              AND r.status = 'active'

            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);

        return $statement->fetchColumn()
            !== false;
    }
}