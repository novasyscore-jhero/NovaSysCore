<?php

namespace NovaSysCore\Context;

use NovaSysCore\Database;
use PDO;

class BranchContextSelector
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function availableBranches(
        int $userId,
        int $companyId
    ): array {
        if (
            $userId <= 0
            || $companyId <= 0
        ) {
            return [];
        }

        if (!$this->isUserActive($userId)) {
            return [];
        }

        if (!$this->isCompanyActive($companyId)) {
            return [];
        }

        /*
         * Super Administrator puede operar sobre
         * cualquier sucursal activa de la empresa.
         */
        if ($this->isSuperAdministrator($userId)) {
            return $this->allActiveBranches(
                $companyId
            );
        }

        $userCompanyId =
            $this->getActiveUserCompanyId(
                $userId,
                $companyId
            );

        if ($userCompanyId === null) {
            return [];
        }

        /*
         * Si cualquiera de sus roles empresariales
         * activos tiene alcance "all", puede utilizar
         * todas las sucursales activas de la empresa.
         */
        if (
            $this->hasAllBranchScope(
                $userCompanyId,
                $companyId
            )
        ) {
            return $this->allActiveBranches(
                $companyId
            );
        }

        /*
         * En caso contrario combinamos las sucursales
         * de todos los roles con branch_scope=selected.
         */
        return $this->selectedBranches(
            $userCompanyId,
            $companyId
        );
    }

    private function allActiveBranches(
        int $companyId
    ): array {
        $statement = $this->pdo->prepare("
            SELECT
                id,
                company_id,
                name,
                code,
                slug

            FROM branches

            WHERE company_id = :company_id
              AND status = 'active'

            ORDER BY
                name ASC,
                id ASC
        ");

        $statement->execute([
            'company_id' => $companyId,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    private function selectedBranches(
        int $userCompanyId,
        int $companyId
    ): array {
        $statement = $this->pdo->prepare("
            SELECT DISTINCT
                b.id,
                b.company_id,
                b.name,
                b.code,
                b.slug

            FROM user_company_roles ucr

            INNER JOIN roles r
                ON r.id = ucr.role_id

            INNER JOIN user_company_branches ucb
                ON ucb.user_company_role_id = ucr.id

            INNER JOIN branches b
                ON b.id = ucb.branch_id

            WHERE ucr.user_company_id = :user_company_id

              AND ucr.branch_scope = 'selected'

              AND r.company_id = :company_id
              AND r.status = 'active'

              AND b.company_id = :company_id
              AND b.status = 'active'

            ORDER BY
                b.name ASC,
                b.id ASC
        ");

        $statement->execute([
            'user_company_id' => $userCompanyId,
            'company_id' => $companyId,
        ]);

        return $statement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    private function hasAllBranchScope(
        int $userCompanyId,
        int $companyId
    ): bool {
        $statement = $this->pdo->prepare("
            SELECT 1

            FROM user_company_roles ucr

            INNER JOIN roles r
                ON r.id = ucr.role_id

            WHERE ucr.user_company_id = :user_company_id

              AND ucr.branch_scope = 'all'

              AND r.company_id = :company_id
              AND r.status = 'active'

            LIMIT 1
        ");

        $statement->execute([
            'user_company_id' => $userCompanyId,
            'company_id' => $companyId,
        ]);

        return $statement->fetchColumn()
            !== false;
    }

    private function getActiveUserCompanyId(
        int $userId,
        int $companyId
    ): ?int {
        $statement = $this->pdo->prepare("
            SELECT id

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

        $id = $statement->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
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