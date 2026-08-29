<?php

namespace NovaSysCore\Security;

use NovaSysCore\Database;
use PDO;

class AuthorizationService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Valida permisos globales del sistema.
     */
    public function canSystem(
        int $userId,
        string $permissionSlug
    ): bool {
        if (!$this->isUserActive($userId)) {
            return false;
        }

        if ($this->isSuperAdministrator($userId)) {
            return true;
        }

        $sql = "
            SELECT 1
            FROM user_system_roles usr

            INNER JOIN roles r
                ON r.id = usr.role_id

            INNER JOIN role_permissions rp
                ON rp.role_id = r.id

            INNER JOIN permissions p
                ON p.id = rp.permission_id

            WHERE usr.user_id = :user_id

              AND r.company_id IS NULL
              AND r.is_system = TRUE
              AND r.status = 'active'

              AND p.slug = :permission_slug
              AND p.status = 'active'

            LIMIT 1
        ";

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'user_id' => $userId,
            'permission_slug' => $permissionSlug,
        ]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * Valida permisos dentro de una empresa.
     */
    public function can(
        int $userId,
        int $companyId,
        string $permissionSlug,
        ?int $branchId = null
    ): bool {
        if (!$this->isUserActive($userId)) {
            return false;
        }

        if ($this->isSuperAdministrator($userId)) {
            return true;
        }

        if (!$this->isCompanyActive($companyId)) {
            return false;
        }

        if ($branchId !== null) {
            if (!$this->branchBelongsToCompany(
                $branchId,
                $companyId
            )) {
                return false;
            }
        }

        $userCompanyId = $this->getActiveUserCompanyId(
            $userId,
            $companyId
        );

        if ($userCompanyId === null) {
            return false;
        }

        $sql = "
            SELECT
                ucr.id AS user_company_role_id,
                ucr.branch_scope

            FROM user_company_roles ucr

            INNER JOIN roles r
                ON r.id = ucr.role_id

            INNER JOIN role_permissions rp
                ON rp.role_id = r.id

            INNER JOIN permissions p
                ON p.id = rp.permission_id

            WHERE ucr.user_company_id = :user_company_id

              AND r.company_id = :company_id
              AND r.status = 'active'

              AND p.slug = :permission_slug
              AND p.status = 'active'
        ";

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'user_company_id' => $userCompanyId,
            'company_id' => $companyId,
            'permission_slug' => $permissionSlug,
        ]);

        $roles = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roles as $role) {
            $branchScope = $role['branch_scope'];

            if ($branchId === null) {
                return true;
            }

            if ($branchScope === 'all') {
                return true;
            }

            if ($branchScope !== 'selected') {
                continue;
            }

            if ($this->roleHasBranch(
                (int) $role['user_company_role_id'],
                $branchId
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comprueba si el usuario existe y está activo.
     */
    private function isUserActive(int $userId): bool
    {
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

    /**
     * Detecta al Super Administrator global.
     *
     * Este rol no depende de role_permissions.
     */
    private function isSuperAdministrator(int $userId): bool
    {
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

        return (bool) $statement->fetchColumn();
    }

    /**
     * Comprueba si una empresa está activa.
     */
    private function isCompanyActive(int $companyId): bool
    {
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

    /**
     * Obtiene la membresía activa del usuario en la empresa.
     */
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

    /**
     * Verifica que una sucursal realmente pertenezca
     * a la empresa solicitada.
     */
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

    /**
     * Comprueba si un rol con branch_scope = selected
     * tiene asignada específicamente la sucursal.
     */
    private function roleHasBranch(
        int $userCompanyRoleId,
        int $branchId
    ): bool {
        $statement = $this->pdo->prepare("
            SELECT 1
            FROM user_company_branches
            WHERE user_company_role_id = :user_company_role_id
              AND branch_id = :branch_id
            LIMIT 1
        ");

        $statement->execute([
            'user_company_role_id' => $userCompanyRoleId,
            'branch_id' => $branchId,
        ]);

        return (bool) $statement->fetchColumn();
    }
}