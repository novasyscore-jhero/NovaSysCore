<?php

namespace App\Services\Users;

use App\Exceptions\Users\BranchNotAvailableException;
use App\Exceptions\Users\InvalidBranchScopeException;
use App\Exceptions\Users\RoleAlreadyAssignedException;
use App\Exceptions\Users\RoleNotAvailableException;
use NovaSysCore\Database;
use PDO;
use PDOException;
use Throwable;

class CompanyUserRoleService
{
    public function assign(
        int $userCompanyId,
        int $companyId,
        int $roleId,
        string $branchScope,
        array $branchIds = []
    ): int {
        $branchScope = trim(
            strtolower($branchScope)
        );

        if (
            !in_array(
                $branchScope,
                ['all', 'selected'],
                true
            )
        ) {
            throw new InvalidBranchScopeException();
        }

        /*
         * Normalizamos IDs para evitar duplicados
         * dentro de la misma operación.
         */
        $branchIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    $branchIds
                )
            )
        );

        $branchIds = array_values(
            array_filter(
                $branchIds,
                static fn(int $branchId): bool =>
                    $branchId > 0
            )
        );

        /*
         * Un alcance "all" no necesita asignaciones
         * individuales de sucursales.
         */
        if ($branchScope === 'all') {
            $branchIds = [];
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * =================================================
             * MEMBRESÍA
             * =================================================
             */

            $statement = $pdo->prepare("
                SELECT id
                FROM user_companies
                WHERE id = :user_company_id
                  AND company_id = :company_id
                  AND status = 'active'
                LIMIT 1
                FOR UPDATE
            ");

            $statement->execute([
                'user_company_id' => $userCompanyId,
                'company_id' => $companyId,
            ]);

            if (!$statement->fetch()) {
                throw new RoleNotAvailableException();
            }

            /*
             * =================================================
             * ROL EMPRESARIAL
             * =================================================
             *
             * company_id obligatorio impide que un rol global
             * pueda entrar por esta operación.
             */

            $statement = $pdo->prepare("
                SELECT id
                FROM roles
                WHERE id = :role_id
                  AND company_id = :company_id
                  AND status = 'active'
                LIMIT 1
                FOR UPDATE
            ");

            $statement->execute([
                'role_id' => $roleId,
                'company_id' => $companyId,
            ]);

            if (!$statement->fetch()) {
                throw new RoleNotAvailableException();
            }

            /*
             * =================================================
             * ASIGNACIÓN DUPLICADA
             * =================================================
             */

            $statement = $pdo->prepare("
                SELECT id
                FROM user_company_roles
                WHERE user_company_id = :user_company_id
                  AND role_id = :role_id
                LIMIT 1
                FOR UPDATE
            ");

            $statement->execute([
                'user_company_id' => $userCompanyId,
                'role_id' => $roleId,
            ]);

            if ($statement->fetch()) {
                throw new RoleAlreadyAssignedException();
            }

            /*
             * =================================================
             * SUCURSALES SELECCIONADAS
             * =================================================
             */

            if (
                $branchScope === 'selected'
                && !empty($branchIds)
            ) {
                $placeholders = [];

                $parameters = [
                    'company_id' => $companyId,
                ];

                foreach (
                    $branchIds
                    as $index => $branchId
                ) {
                    $parameter =
                        'branch_' . $index;

                    $placeholders[] =
                        ':' . $parameter;

                    $parameters[$parameter] =
                        $branchId;
                }

                $statement = $pdo->prepare("
                    SELECT id
                    FROM branches
                    WHERE company_id = :company_id
                      AND status = 'active'
                      AND id IN (
                        " . implode(
                    ', ',
                    $placeholders
                ) . "
                      )
                    FOR UPDATE
                ");

                $statement->execute(
                    $parameters
                );

                $validBranchIds = array_map(
                    'intval',
                    $statement->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                );

                sort($validBranchIds);

                $expectedBranchIds =
                    $branchIds;

                sort($expectedBranchIds);

                if (
                    $validBranchIds
                    !== $expectedBranchIds
                ) {
                    throw new BranchNotAvailableException();
                }
            }

            /*
             * =================================================
             * CREACIÓN DE LA ASIGNACIÓN
             * =================================================
             */

            $statement = $pdo->prepare("
                INSERT INTO user_company_roles (
                    user_company_id,
                    role_id,
                    branch_scope
                )
                VALUES (
                    :user_company_id,
                    :role_id,
                    :branch_scope
                )
            ");

            $statement->execute([
                'user_company_id' => $userCompanyId,
                'role_id' => $roleId,
                'branch_scope' => $branchScope,
            ]);

            $assignmentId =
                (int) $pdo->lastInsertId();

            /*
             * =================================================
             * ASIGNACIÓN DE SUCURSALES
             * =================================================
             */

            if (
                $branchScope === 'selected'
                && !empty($branchIds)
            ) {
                $statement = $pdo->prepare("
                    INSERT INTO user_company_branches (
                        user_company_role_id,
                        branch_id
                    )
                    VALUES (
                        :user_company_role_id,
                        :branch_id
                    )
                ");

                foreach (
                    $branchIds
                    as $branchId
                ) {
                    $statement->execute([
                        'user_company_role_id'
                        => $assignmentId,
                        'branch_id'
                        => $branchId,
                    ]);
                }
            }

            $pdo->commit();

            return $assignmentId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /*
             * Una carrera concurrente puede superar la
             * comprobación previa y llegar hasta la restricción
             * UNIQUE(user_company_id, role_id).
             *
             * MariaDB/MySQL:
             * SQLSTATE 23000 = integrity constraint violation
             * Driver code 1062 = duplicate entry
             *
             * Solo traducimos específicamente la UNIQUE
             * correspondiente a la asignación de roles.
             */
            if (
                $exception instanceof PDOException
                && $exception->getCode() === '23000'
                && isset($exception->errorInfo[1])
                && (int) $exception->errorInfo[1] === 1062
                && str_contains(
                    $exception->getMessage(),
                    'uq_user_company_roles_membership_role'
                )
            ) {
                throw new RoleAlreadyAssignedException(
                    previous: $exception
                );
            }

            throw $exception;
        }
    }
}