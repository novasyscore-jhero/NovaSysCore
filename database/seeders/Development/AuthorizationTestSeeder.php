<?php

namespace NovaSysCore\Database\Seeders\Development;

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Seeder;
use PDO;
use RuntimeException;

class AuthorizationTestSeeder extends Seeder
{
    public function run(): void
    {
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * =========================================================
             * 1. EMPRESAS
             * =========================================================
             */

            $this->upsertCompany(
                $pdo,
                'Empresa Alpha',
                'empresa-alpha',
                'ALPHA'
            );

            $this->upsertCompany(
                $pdo,
                'Empresa Beta',
                'empresa-beta',
                'BETA'
            );

            $companyAlphaId = $this->getCompanyId(
                $pdo,
                'empresa-alpha'
            );

            $companyBetaId = $this->getCompanyId(
                $pdo,
                'empresa-beta'
            );

            /*
             * =========================================================
             * 2. SUCURSALES
             * =========================================================
             */

            $this->upsertBranch(
                $pdo,
                $companyAlphaId,
                'Sucursal Centro',
                'CENTRO',
                'sucursal-centro'
            );

            $this->upsertBranch(
                $pdo,
                $companyAlphaId,
                'Sucursal Norte',
                'NORTE',
                'sucursal-norte'
            );

            $this->upsertBranch(
                $pdo,
                $companyBetaId,
                'Sucursal Beta',
                'BETA-01',
                'sucursal-beta'
            );

            $branchCentroId = $this->getBranchId(
                $pdo,
                $companyAlphaId,
                'sucursal-centro'
            );

            /*
             * =========================================================
             * 3. USUARIO DE PRUEBA
             * =========================================================
             */

            $email = 'authorization.test@novasyscore.local';

            $this->upsertUser(
                $pdo,
                'Usuario',
                'Prueba',
                $email
            );

            $userId = $this->getUserId(
                $pdo,
                $email
            );

            /*
             * =========================================================
             * 4. ROL EMPRESARIAL PARA EMPRESA ALPHA
             * =========================================================
             */

            $this->upsertCompanyRole(
                $pdo,
                $companyAlphaId,
                'Administrador Alpha',
                'administrador-alpha'
            );

            $roleId = $this->getCompanyRoleId(
                $pdo,
                $companyAlphaId,
                'administrador-alpha'
            );

            /*
             * =========================================================
             * 5. PERMISO users.view
             * =========================================================
             */

            $permissionId = $this->getPermissionId(
                $pdo,
                'users.view'
            );

            $statement = $pdo->prepare("
                INSERT INTO role_permissions (
                    role_id,
                    permission_id
                ) VALUES (
                    :role_id,
                    :permission_id
                )
                ON DUPLICATE KEY UPDATE
                    role_id = VALUES(role_id)
            ");

            $statement->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);

            /*
             * =========================================================
             * 6. MEMBRESÍA DEL USUARIO EN EMPRESA ALPHA
             * =========================================================
             */

            $statement = $pdo->prepare("
                INSERT INTO user_companies (
                    user_id,
                    company_id,
                    status
                ) VALUES (
                    :user_id,
                    :company_id,
                    'active'
                )
                ON DUPLICATE KEY UPDATE
                    status = 'active'
            ");

            $statement->execute([
                'user_id' => $userId,
                'company_id' => $companyAlphaId,
            ]);

            $userCompanyId = $this->getUserCompanyId(
                $pdo,
                $userId,
                $companyAlphaId
            );

            /*
             * =========================================================
             * 7. ASIGNACIÓN DEL ROL
             *
             * Solo permitiremos inicialmente sucursales seleccionadas.
             * =========================================================
             */

            $statement = $pdo->prepare("
                INSERT INTO user_company_roles (
                    user_company_id,
                    role_id,
                    branch_scope
                ) VALUES (
                    :user_company_id,
                    :role_id,
                    'selected'
                )
                ON DUPLICATE KEY UPDATE
                    branch_scope = 'selected'
            ");

            $statement->execute([
                'user_company_id' => $userCompanyId,
                'role_id' => $roleId,
            ]);

            $userCompanyRoleId = $this->getUserCompanyRoleId(
                $pdo,
                $userCompanyId,
                $roleId
            );

            /*
             * =========================================================
             * 8. SOLO SUCURSAL CENTRO
             * =========================================================
             */

            $statement = $pdo->prepare("
                INSERT INTO user_company_branches (
                    user_company_role_id,
                    branch_id
                ) VALUES (
                    :user_company_role_id,
                    :branch_id
                )
                ON DUPLICATE KEY UPDATE
                    branch_id = VALUES(branch_id)
            ");

            $statement->execute([
                'user_company_role_id' => $userCompanyRoleId,
                'branch_id' => $branchCentroId,
            ]);

            $pdo->commit();

            echo PHP_EOL;
            echo '[OK] Escenario de autorización creado.' . PHP_EOL;
            echo PHP_EOL;
            echo 'Usuario: authorization.test@novasyscore.local' . PHP_EOL;
            echo 'Password: Test1234!' . PHP_EOL;
            echo 'Empresa autorizada: Empresa Alpha' . PHP_EOL;
            echo 'Sucursal autorizada: Sucursal Centro' . PHP_EOL;
            echo 'Permiso: users.view' . PHP_EOL;
            echo 'branch_scope: selected' . PHP_EOL;
            echo PHP_EOL;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function upsertCompany(
        PDO $pdo,
        string $name,
        string $slug,
        string $code
    ): void {
        $statement = $pdo->prepare("
            INSERT INTO companies (
                name,
                slug,
                code,
                status
            ) VALUES (
                :name,
                :slug,
                :code,
                'active'
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                status = 'active'
        ");

        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'code' => $code,
        ]);
    }

    private function getCompanyId(
        PDO $pdo,
        string $slug
    ): int {
        $statement = $pdo->prepare("
            SELECT id
            FROM companies
            WHERE slug = :slug
            LIMIT 1
        ");

        $statement->execute([
            'slug' => $slug,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                "No se encontró la empresa {$slug}."
            );
        }

        return (int) $id;
    }

    private function upsertBranch(
        PDO $pdo,
        int $companyId,
        string $name,
        string $code,
        string $slug
    ): void {
        $statement = $pdo->prepare("
            INSERT INTO branches (
                company_id,
                name,
                code,
                slug,
                status
            ) VALUES (
                :company_id,
                :name,
                :code,
                :slug,
                'active'
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                status = 'active'
        ");

        $statement->execute([
            'company_id' => $companyId,
            'name' => $name,
            'code' => $code,
            'slug' => $slug,
        ]);
    }

    private function getBranchId(
        PDO $pdo,
        int $companyId,
        string $slug
    ): int {
        $statement = $pdo->prepare("
            SELECT id
            FROM branches
            WHERE company_id = :company_id
              AND slug = :slug
            LIMIT 1
        ");

        $statement->execute([
            'company_id' => $companyId,
            'slug' => $slug,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                "No se encontró la sucursal {$slug}."
            );
        }

        return (int) $id;
    }

    private function upsertUser(
        PDO $pdo,
        string $name,
        string $lastName,
        string $email
    ): void {
        $statement = $pdo->prepare("
            INSERT INTO users (
                name,
                last_name,
                email,
                password_hash,
                status
            ) VALUES (
                :name,
                :last_name,
                :email,
                :password_hash,
                'active'
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                last_name = VALUES(last_name),
                status = 'active'
        ");

        $statement->execute([
            'name' => $name,
            'last_name' => $lastName,
            'email' => strtolower(trim($email)),
            'password_hash' => password_hash(
                'Test1234!',
                PASSWORD_DEFAULT
            ),
        ]);
    }

    private function getUserId(
        PDO $pdo,
        string $email
    ): int {
        $statement = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $statement->execute([
            'email' => strtolower(trim($email)),
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'No se encontró el usuario de prueba.'
            );
        }

        return (int) $id;
    }

    private function upsertCompanyRole(
        PDO $pdo,
        int $companyId,
        string $name,
        string $slug
    ): void {
        $statement = $pdo->prepare("
            INSERT INTO roles (
                company_id,
                name,
                slug,
                description,
                is_system,
                status
            ) VALUES (
                :company_id,
                :name,
                :slug,
                'Rol empresarial utilizado para pruebas de autorización.',
                FALSE,
                'active'
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                is_system = FALSE,
                status = 'active'
        ");

        $statement->execute([
            'company_id' => $companyId,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function getCompanyRoleId(
        PDO $pdo,
        int $companyId,
        string $slug
    ): int {
        $statement = $pdo->prepare("
            SELECT id
            FROM roles
            WHERE company_id = :company_id
              AND slug = :slug
            LIMIT 1
        ");

        $statement->execute([
            'company_id' => $companyId,
            'slug' => $slug,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'No se encontró el rol empresarial de prueba.'
            );
        }

        return (int) $id;
    }

    private function getPermissionId(
        PDO $pdo,
        string $slug
    ): int {
        $statement = $pdo->prepare("
            SELECT id
            FROM permissions
            WHERE slug = :slug
              AND status = 'active'
            LIMIT 1
        ");

        $statement->execute([
            'slug' => $slug,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                "No se encontró el permiso {$slug}. Ejecuta primero db:seed."
            );
        }

        return (int) $id;
    }

    private function getUserCompanyId(
        PDO $pdo,
        int $userId,
        int $companyId
    ): int {
        $statement = $pdo->prepare("
            SELECT id
            FROM user_companies
            WHERE user_id = :user_id
              AND company_id = :company_id
            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'No se encontró la membresía empresarial.'
            );
        }

        return (int) $id;
    }

    private function getUserCompanyRoleId(
        PDO $pdo,
        int $userCompanyId,
        int $roleId
    ): int {
        $statement = $pdo->prepare("
            SELECT id
            FROM user_company_roles
            WHERE user_company_id = :user_company_id
              AND role_id = :role_id
            LIMIT 1
        ");

        $statement->execute([
            'user_company_id' => $userCompanyId,
            'role_id' => $roleId,
        ]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'No se encontró la asignación del rol empresarial.'
            );
        }

        return (int) $id;
    }
}