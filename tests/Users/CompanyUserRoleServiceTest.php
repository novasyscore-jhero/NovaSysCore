<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Exceptions\Users\BranchNotAvailableException;
use App\Exceptions\Users\InvalidBranchScopeException;
use App\Exceptions\Users\RoleAlreadyAssignedException;
use App\Exceptions\Users\RoleNotAvailableException;
use App\Services\Users\CompanyUserRoleService;
use NovaSysCore\Database;


echo PHP_EOL;
echo "NovaSysCore - Company User Role Service Test" . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;

$correct = 0;
$failed = 0;

function checkCompanyUserRoleService(
    string $label,
    bool $condition
): void {
    global $correct, $failed;

    if ($condition) {
        echo "[OK] {$label}" . PHP_EOL;
        $correct++;

        return;
    }

    echo "[FAIL] {$label}" . PHP_EOL;
    $failed++;
}

$pdo = Database::connection();

$userId = null;

$roleIds = [];

try {
    $service =
        new CompanyUserRoleService();

    /*
     * =====================================================
     * FIXTURE: USUARIO TEMPORAL
     * =====================================================
     */

    $temporaryEmail =
        'company-user-role-service-'
        . bin2hex(random_bytes(8))
        . '@novasyscore.local';

    $statement = $pdo->prepare("
        INSERT INTO users (
            name,
            email,
            password_hash,
            status
        )
        VALUES (
            'Usuario Roles',
            :email,
            :password_hash,
            'active'
        )
    ");

    $statement->execute([
        'email' => $temporaryEmail,
        'password_hash' => password_hash(
            'Test1234!',
            PASSWORD_DEFAULT
        ),
    ]);

    $userId =
        (int) $pdo->lastInsertId();

    checkCompanyUserRoleService(
        'Usuario temporal fue creado',
        $userId > 0
    );

    /*
     * Membresía Alpha.
     */

    $statement = $pdo->prepare("
        INSERT INTO user_companies (
            user_id,
            company_id,
            status
        )
        VALUES (
            :user_id,
            1,
            'active'
        )
    ");

    $statement->execute([
        'user_id' => $userId,
    ]);

    $alphaMembershipId =
        (int) $pdo->lastInsertId();

    /*
     * =====================================================
     * FIXTURE: ROLES EMPRESARIALES
     * =====================================================
     */

    $statement = $pdo->prepare("
        INSERT INTO roles (
            company_id,
            name,
            slug,
            status
        )
        VALUES (
            :company_id,
            :name,
            :slug,
            'active'
        )
    ");

    $allRoleName =
        'Rol All Temporal '
        . bin2hex(random_bytes(4));

    $statement->execute([
        'company_id' => 1,
        'name' => $allRoleName,
        'slug' =>
            'rol-all-temporal-'
            . bin2hex(random_bytes(4)),
    ]);

    $allRoleId =
        (int) $pdo->lastInsertId();

    $roleIds[] = $allRoleId;

    $selectedRoleName =
        'Rol Selected Temporal '
        . bin2hex(random_bytes(4));

    $statement->execute([
        'company_id' => 1,
        'name' => $selectedRoleName,
        'slug' =>
            'rol-selected-temporal-'
            . bin2hex(random_bytes(4)),
    ]);

    $selectedRoleId =
        (int) $pdo->lastInsertId();

    $roleIds[] = $selectedRoleId;

    $emptyRoleName =
        'Rol Empty Temporal '
        . bin2hex(random_bytes(4));

    $statement->execute([
        'company_id' => 1,
        'name' => $emptyRoleName,
        'slug' =>
            'rol-empty-temporal-'
            . bin2hex(random_bytes(4)),
    ]);

    $emptyRoleId =
        (int) $pdo->lastInsertId();

    $roleIds[] = $emptyRoleId;

    /*
     * =====================================================
     * 1. ALCANCE ALL
     * =====================================================
     */

    $allAssignmentId =
        $service->assign(
            $alphaMembershipId,
            1,
            $allRoleId,
            'all',
            [1, 2]
        );

    checkCompanyUserRoleService(
        'Asigna rol con alcance all',
        $allAssignmentId > 0
    );

    $statement = $pdo->prepare("
        SELECT branch_scope
        FROM user_company_roles
        WHERE id = :id
        LIMIT 1
    ");

    $statement->execute([
        'id' => $allAssignmentId,
    ]);

    checkCompanyUserRoleService(
        'Alcance all se guarda correctamente',
        $statement->fetchColumn() === 'all'
    );

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_company_branches
        WHERE user_company_role_id = :id
    ");

    $statement->execute([
        'id' => $allAssignmentId,
    ]);

    checkCompanyUserRoleService(
        'Alcance all no crea sucursales individuales',
        (int) $statement->fetchColumn() === 0
    );

    /*
     * =====================================================
     * 2. SELECTED CON SUCURSALES
     * =====================================================
     */

    $selectedAssignmentId =
        $service->assign(
            $alphaMembershipId,
            1,
            $selectedRoleId,
            'selected',
            [1, 2, 1]
        );

    checkCompanyUserRoleService(
        'Asigna rol con alcance selected',
        $selectedAssignmentId > 0
    );

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_company_branches
        WHERE user_company_role_id = :id
    ");

    $statement->execute([
        'id' => $selectedAssignmentId,
    ]);

    checkCompanyUserRoleService(
        'Selected elimina IDs duplicados',
        (int) $statement->fetchColumn() === 2
    );

    $statement = $pdo->prepare("
        SELECT b.code
        FROM user_company_branches ucb

        INNER JOIN branches b
            ON b.id = ucb.branch_id

        WHERE ucb.user_company_role_id = :id

        ORDER BY b.code
    ");

    $statement->execute([
        'id' => $selectedAssignmentId,
    ]);

    $branchCodes =
        $statement->fetchAll(
            PDO::FETCH_COLUMN
        );

    checkCompanyUserRoleService(
        'Selected conserva las sucursales correctas',
        $branchCodes === [
            'CENTRO',
            'NORTE',
        ]
    );

    /*
     * =====================================================
     * 3. SELECTED VACÍO
     * =====================================================
     */

    $emptyAssignmentId =
        $service->assign(
            $alphaMembershipId,
            1,
            $emptyRoleId,
            'selected',
            []
        );

    checkCompanyUserRoleService(
        'Selected vacío crea la asignación',
        $emptyAssignmentId > 0
    );

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_company_branches
        WHERE user_company_role_id = :id
    ");

    $statement->execute([
        'id' => $emptyAssignmentId,
    ]);

    checkCompanyUserRoleService(
        'Selected vacío no crea sucursales',
        (int) $statement->fetchColumn() === 0
    );

    /*
     * =====================================================
     * 4. SCOPE INVÁLIDO
     * =====================================================
     */

    $invalidScopeDetected = false;

    try {
        $service->assign(
            $alphaMembershipId,
            1,
            $selectedRoleId,
            'invalid',
            []
        );
    } catch (
        InvalidBranchScopeException $exception
    ) {
        $invalidScopeDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza branch_scope inválido',
        $invalidScopeDetected
    );

    /*
     * =====================================================
     * 5. ROL DUPLICADO
     * =====================================================
     */

    $duplicateDetected = false;

    try {
        $service->assign(
            $alphaMembershipId,
            1,
            $allRoleId,
            'all'
        );
    } catch (
        RoleAlreadyAssignedException $exception
    ) {
        $duplicateDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza rol empresarial duplicado',
        $duplicateDetected
    );

    /*
     * La operación fallida no debe duplicar
     * la asignación existente.
     */

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_company_roles
        WHERE user_company_id = :membership_id
          AND role_id = :role_id
    ");

    $statement->execute([
        'membership_id'
        => $alphaMembershipId,
        'role_id'
        => $allRoleId,
    ]);

    checkCompanyUserRoleService(
        'Rol duplicado no altera la asignación existente',
        (int) $statement->fetchColumn() === 1
    );

    /*
     * =====================================================
     * 6. SUCURSAL DE OTRA EMPRESA
     * =====================================================
     */

    $foreignBranchRoleName =
        'Rol Foreign Branch '
        . bin2hex(random_bytes(4));

    $statement = $pdo->prepare("
        INSERT INTO roles (
            company_id,
            name,
            slug,
            status
        )
        VALUES (
            1,
            :name,
            :slug,
            'active'
        )
    ");

    $statement->execute([
        'name' => $foreignBranchRoleName,
        'slug' =>
            'rol-foreign-branch-'
            . bin2hex(random_bytes(4)),
    ]);

    $foreignBranchRoleId =
        (int) $pdo->lastInsertId();

    $roleIds[] =
        $foreignBranchRoleId;

    $foreignBranchDetected = false;

    try {
        /*
         * branch_id 3 pertenece a Empresa Beta.
         */
        $service->assign(
            $alphaMembershipId,
            1,
            $foreignBranchRoleId,
            'selected',
            [3]
        );
    } catch (
        BranchNotAvailableException $exception
    ) {
        $foreignBranchDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza sucursal perteneciente a otra empresa',
        $foreignBranchDetected
    );

    /*
     * Comprobación explícita del rollback.
     */

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_company_roles
        WHERE user_company_id = :membership_id
          AND role_id = :role_id
    ");

    $statement->execute([
        'membership_id'
        => $alphaMembershipId,
        'role_id'
        => $foreignBranchRoleId,
    ]);

    checkCompanyUserRoleService(
        'Error de sucursal no deja asignación parcial',
        (int) $statement->fetchColumn() === 0
    );

    /*
     * =====================================================
     * 7. ROL GLOBAL
     * =====================================================
     *
     * Los roles globales tienen company_id = NULL.
     * Esta operación administra exclusivamente
     * roles empresariales.
     */

    $globalRoleDetected = false;

    try {
        /*
         * role_id 1 = Super Administrator.
         */
        $service->assign(
            $alphaMembershipId,
            1,
            1,
            'all'
        );
    } catch (
        RoleNotAvailableException $exception
    ) {
        $globalRoleDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza rol global desde asignación empresarial',
        $globalRoleDetected
    );

    /*
     * =====================================================
     * 8. ROL DE OTRA EMPRESA
     * =====================================================
     */

    $betaRoleName =
        'Rol Beta Hostil '
        . bin2hex(random_bytes(4));

    $statement = $pdo->prepare("
    INSERT INTO roles (
        company_id,
        name,
        slug,
        status
    )
    VALUES (
        2,
        :name,
        :slug,
        'active'
    )
");

    $statement->execute([
        'name' => $betaRoleName,
        'slug' =>
            'rol-beta-hostil-'
            . bin2hex(random_bytes(4)),
    ]);

    $betaRoleId =
        (int) $pdo->lastInsertId();

    $roleIds[] =
        $betaRoleId;

    $foreignRoleDetected = false;

    try {
        $service->assign(
            $alphaMembershipId,
            1,
            $betaRoleId,
            'all'
        );
    } catch (
        RoleNotAvailableException $exception
    ) {
        $foreignRoleDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza rol perteneciente a otra empresa',
        $foreignRoleDetected
    );

    /*
     * =====================================================
     * 9. ROL INACTIVO
     * =====================================================
     */

    $inactiveRoleName =
        'Rol Inactivo Temporal '
        . bin2hex(random_bytes(4));

    $statement = $pdo->prepare("
    INSERT INTO roles (
        company_id,
        name,
        slug,
        status
    )
    VALUES (
        1,
        :name,
        :slug,
        'inactive'
    )
");

    $statement->execute([
        'name' => $inactiveRoleName,
        'slug' =>
            'rol-inactivo-temporal-'
            . bin2hex(random_bytes(4)),
    ]);

    $inactiveRoleId =
        (int) $pdo->lastInsertId();

    $roleIds[] =
        $inactiveRoleId;

    $inactiveRoleDetected = false;

    try {
        $service->assign(
            $alphaMembershipId,
            1,
            $inactiveRoleId,
            'all'
        );
    } catch (
        RoleNotAvailableException $exception
    ) {
        $inactiveRoleDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza rol empresarial inactivo',
        $inactiveRoleDetected
    );

    /*
     * =====================================================
     * 10. MEMBRESÍA DE OTRA EMPRESA
     * =====================================================
     */

    $statement = $pdo->prepare("
    INSERT INTO user_companies (
        user_id,
        company_id,
        status
    )
    VALUES (
        :user_id,
        2,
        'active'
    )
");

    $statement->execute([
        'user_id' => $userId,
    ]);

    $betaMembershipId =
        (int) $pdo->lastInsertId();

    $foreignMembershipDetected = false;

    try {
        /*
         * Intentamos utilizar una membresía Beta
         * dentro del contexto empresarial Alpha.
         */
        $service->assign(
            $betaMembershipId,
            1,
            $allRoleId,
            'all'
        );
    } catch (
        RoleNotAvailableException $exception
    ) {
        $foreignMembershipDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza membresía perteneciente a otra empresa',
        $foreignMembershipDetected
    );

    /*
     * =====================================================
     * 11. MEMBRESÍA INACTIVA
     * =====================================================
     */

    $statement = $pdo->prepare("
    UPDATE user_companies
    SET status = 'inactive'
    WHERE id = :id
");

    $statement->execute([
        'id' => $betaMembershipId,
    ]);

    $inactiveMembershipDetected = false;

    try {
        /*
         * Aquí companyId sí coincide con Beta,
         * pero la membresía está inactiva.
         */
        $service->assign(
            $betaMembershipId,
            2,
            $betaRoleId,
            'all'
        );
    } catch (
        RoleNotAvailableException $exception
    ) {
        $inactiveMembershipDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza membresía empresarial inactiva',
        $inactiveMembershipDetected
    );

    /*
     * =====================================================
     * 12. SUCURSAL INACTIVA
     * =====================================================
     *
     * No modificamos una sucursal persistente real.
     * Creamos una temporal para mantener el test aislado.
     */

    $inactiveBranchCode =
        'INACTIVE-'
        . strtoupper(
            bin2hex(random_bytes(4))
        );

    $inactiveBranchSlug =
        'inactive-'
        . bin2hex(random_bytes(4));

    $statement = $pdo->prepare("
    INSERT INTO branches (
        company_id,
        name,
        code,
        slug,
        status
    )
    VALUES (
        1,
        'Sucursal Inactiva Temporal',
        :code,
        :slug,
        'inactive'
    )
");

    $statement->execute([
        'code' => $inactiveBranchCode,
        'slug' => $inactiveBranchSlug,
    ]);

    $inactiveBranchId =
        (int) $pdo->lastInsertId();

    /*
     * Necesitamos un rol Alpha todavía no asignado.
     */

    $inactiveBranchRoleName =
        'Rol Sucursal Inactiva '
        . bin2hex(random_bytes(4));

    $statement = $pdo->prepare("
    INSERT INTO roles (
        company_id,
        name,
        slug,
        status
    )
    VALUES (
        1,
        :name,
        :slug,
        'active'
    )
");

    $statement->execute([
        'name' => $inactiveBranchRoleName,
        'slug' =>
            'rol-sucursal-inactiva-'
            . bin2hex(random_bytes(4)),
    ]);

    $inactiveBranchRoleId =
        (int) $pdo->lastInsertId();

    $roleIds[] =
        $inactiveBranchRoleId;

    $inactiveBranchDetected = false;

    try {
        $service->assign(
            $alphaMembershipId,
            1,
            $inactiveBranchRoleId,
            'selected',
            [$inactiveBranchId]
        );
    } catch (
        BranchNotAvailableException $exception
    ) {
        $inactiveBranchDetected = true;
    }

    checkCompanyUserRoleService(
        'Rechaza sucursal inactiva',
        $inactiveBranchDetected
    );

    /*
     * Comprobamos nuevamente atomicidad.
     */

    $statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM user_company_roles
    WHERE user_company_id = :membership_id
      AND role_id = :role_id
");

    $statement->execute([
        'membership_id'
        => $alphaMembershipId,
        'role_id'
        => $inactiveBranchRoleId,
    ]);

    checkCompanyUserRoleService(
        'Sucursal inactiva no deja asignación parcial',
        (int) $statement->fetchColumn() === 0
    );

} catch (Throwable $exception) {
    $failed++;

    echo '[ERROR] '
        . $exception->getMessage()
        . PHP_EOL;
} finally {
    /*
     * =====================================================
     * CLEANUP
     * =====================================================
     *
     * CompanyUserRoleService controla sus propias
     * transacciones. Eliminamos los fixtures respetando
     * el orden de las claves foráneas.
     */

    if ($userId !== null) {
        try {
            $pdo->beginTransaction();

            /*
             * user_company_branches depende de
             * user_company_roles.
             */

            $statement = $pdo->prepare("
                DELETE ucb
                FROM user_company_branches ucb

                INNER JOIN user_company_roles ucr
                    ON ucr.id =
                        ucb.user_company_role_id

                INNER JOIN user_companies uc
                    ON uc.id =
                        ucr.user_company_id

                WHERE uc.user_id = :user_id
            ");

            $statement->execute([
                'user_id' => $userId,
            ]);

            /*
             * Después eliminamos las asignaciones.
             */

            $statement = $pdo->prepare("
                DELETE ucr
                FROM user_company_roles ucr

                INNER JOIN user_companies uc
                    ON uc.id =
                        ucr.user_company_id

                WHERE uc.user_id = :user_id
            ");

            $statement->execute([
                'user_id' => $userId,
            ]);

            /*
             * Eliminamos membresías.
             */

            $statement = $pdo->prepare("
                DELETE FROM user_companies
                WHERE user_id = :user_id
            ");

            $statement->execute([
                'user_id' => $userId,
            ]);

            /*
             * Eliminamos identidad temporal.
             */

            $statement = $pdo->prepare("
                DELETE FROM users
                WHERE id = :user_id
            ");

            $statement->execute([
                'user_id' => $userId,
            ]);

            /*
             * Eliminamos cualquier sucursal temporal
             * creada por esta prueba.
             */

            if (isset($inactiveBranchId)) {
                $statement = $pdo->prepare("
                DELETE FROM branches
                WHERE id = :id
            ");

                $statement->execute([
                    'id' => $inactiveBranchId,
                ]);
            }

            /*
             * Finalmente eliminamos los roles
             * empresariales temporales.
             */

            if (!empty($roleIds)) {
                $placeholders = [];

                $parameters = [];

                foreach (
                    $roleIds
                    as $index => $roleId
                ) {
                    $parameter =
                        'role_' . $index;

                    $placeholders[] =
                        ':' . $parameter;

                    $parameters[$parameter] =
                        $roleId;
                }

                $statement = $pdo->prepare("
                    DELETE FROM roles
                    WHERE id IN (
                        " . implode(
                    ', ',
                    $placeholders
                ) . "
                    )
                ");

                $statement->execute(
                    $parameters
                );
            }

            $pdo->commit();
        } catch (Throwable $cleanupException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $failed++;

            echo '[ERROR CLEANUP] '
                . $cleanupException->getMessage()
                . PHP_EOL;
        }
    }
}

echo str_repeat('-', 78) . PHP_EOL;
echo "Correctas: {$correct}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;

exit(
    $failed === 0
    ? 0
    : 1
);
