<?php

namespace NovaSysCore\Context;

use NovaSysCore\Database;
use PDO;

class CompanyContextResolver
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function resolve(
        int $userId,
        int $companyId,
        ?int $branchId = null
    ): ?CompanyContext {
        if (
            $userId <= 0
            || $companyId <= 0
        ) {
            return null;
        }

        if (
            $branchId !== null
            && $branchId <= 0
        ) {
            return null;
        }

        if (!$this->isUserActive($userId)) {
            return null;
        }

        if (!$this->isCompanyActive($companyId)) {
            return null;
        }

        $isSuperAdministrator =
            $this->isSuperAdministrator($userId);

        if (
            !$isSuperAdministrator
            && !$this->hasActiveMembership(
                $userId,
                $companyId
            )
        ) {
            return null;
        }

        if (
            $branchId !== null
            && !$this->branchBelongsToCompany(
                $branchId,
                $companyId
            )
        ) {
            return null;
        }

        return new CompanyContext(
            $userId,
            $companyId,
            $branchId
        );
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

        return (bool) $statement->fetchColumn();
    }

    private function isCompanyActive(
        int $companyId
    ): bool {
        $statement = $this->pdo->prepare("
            SELECT 1
            FROM companies
            WHERE id = :company_id
              AND status = 'active'
            LIMIT 1
        ");

        $statement->execute([
            'company_id' => $companyId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function hasActiveMembership(
        int $userId,
        int $companyId
    ): bool {
        $statement = $this->pdo->prepare("
            SELECT 1
            FROM user_companies
            WHERE user_id = :user_id
              AND company_id = :company_id
              AND status = 'active'
            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function branchBelongsToCompany(
        int $branchId,
        int $companyId
    ): bool {
        $statement = $this->pdo->prepare("
            SELECT 1
            FROM branches
            WHERE id = :branch_id
              AND company_id = :company_id
              AND status = 'active'
            LIMIT 1
        ");

        $statement->execute([
            'branch_id' => $branchId,
            'company_id' => $companyId,
        ]);

        return (bool) $statement->fetchColumn();
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

        return $statement->fetchColumn() !== false;
    }
}
