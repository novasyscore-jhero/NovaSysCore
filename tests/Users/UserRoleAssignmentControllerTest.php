<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Http\Controllers\Users\UserController;
use NovaSysCore\Context\CompanyContext;
use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Database;

ob_start();

echo PHP_EOL;
echo "NovaSysCore - User Role Assignment Controller Test" . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;

$correct = 0;
$failed = 0;

function checkUserRoleAssignmentController(
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

function executeUserRoleAssignment(
    UserController $controller,
    string $userId,
    array $post
): array {
    $_POST = $post;

    http_response_code(200);

    ob_start();

    $controller->assignRole($userId);

    return [
        'status' => http_response_code(),
        'output' => trim(
            ob_get_clean()
        ),
    ];
}

$pdo = Database::connection();

$userIds = [];
$roleIds = [];

try {
    /*
     * =====================================================
     * EMPRESAS Y SUCURSALES BASE
     * =====================================================
     */

    $statement = $pdo->prepare("
        SELECT id, slug
        FROM companies
        WHERE slug IN (
            'empresa-alpha',
            'empresa-beta'
        )
          AND status = 'active'
    ");

    $statement->execute();

    $companyIds = [];

    foreach ($statement->fetchAll() as $company) {
        $companyIds[
            $company['slug']
        ] = (int) $company['id'];
    }

    if (
        !isset(
        $companyIds['empresa-alpha'],
        $companyIds['empresa-beta']
    )
    ) {
        throw new RuntimeException(
            'No existen las empresas Alpha y Beta requeridas por la prueba.'
        );
    }

    $alphaCompanyId =
        $companyIds['empresa-alpha'];

    $betaCompanyId =
        $companyIds['empresa-beta'];

    $statement = $pdo->prepare("
        SELECT id, code
        FROM branches
        WHERE company_id = :company_id
          AND code IN (
              'CENTRO',
              'NORTE'
          )
          AND status = 'active'
    ");

    $statement->execute([
        'company_id' => $alphaCompanyId,
    ]);

    $alphaBranches = [];

    foreach ($statement->fetchAll() as $branch) {
        $alphaBranches[
            $branch['code']
        ] = (int) $branch['id'];
    }

    if (
        !isset(
        $alphaBranches['CENTRO'],
        $alphaBranches['NORTE']
    )
    ) {
        throw new RuntimeException(
            'No existen las sucursales Alpha requeridas por la prueba.'
        );
    }

    $statement = $pdo->prepare("
        SELECT id
        FROM branches
        WHERE company_id = :company_id
          AND code = 'BETA-01'
          AND status = 'active'
        LIMIT 1
    ");

    $statement->execute([
        'company_id' => $betaCompanyId,
    ]);

    $betaBranchId =
        $statement->fetchColumn();

    if ($betaBranchId === false) {
        throw new RuntimeException(
            'No existe la sucursal Beta requerida por la prueba.'
        );
    }

    $betaBranchId = (int) $betaBranchId;

    /*
     * =====================================================
     * USUARIOS TEMPORALES
     * =====================================================
     *
     * Uno pertenece a Alpha.
     * Otro pertenece exclusivamente a Beta.
     */

    $statement = $pdo->prepare("
        INSERT INTO users (
            name,
            email,
            password_hash,
            status
        )
        VALUES (
            :name,
            :email,
            :password_hash,
            'active'
        )
    ");

    $statement->execute([
        'name' => 'Role Assignment Alpha',
        'email' =>
            'role-assignment-alpha-'
            . bin2hex(random_bytes(8))
            . '@novasyscore.local',
        'password_hash' =>
            password_hash(
                'Test1234!',
                PASSWORD_DEFAULT
            ),
    ]);

    $alphaUserId =
        (int) $pdo->lastInsertId();

    $userIds[] = $alphaUserId;

    $statement->execute([
        'name' => 'Role Assignment Beta',
        'email' =>
            'role-assignment-beta-'
            . bin2hex(random_bytes(8))
            . '@novasyscore.local',
        'password_hash' =>
            password_hash(
                'Test1234!',
                PASSWORD_DEFAULT
            ),
    ]);

    $betaUserId =
        (int) $pdo->lastInsertId();

    $userIds[] = $betaUserId;

    checkUserRoleAssignmentController(
        'Usuarios temporales fueron creados',
        $alphaUserId > 0
        && $betaUserId > 0
    );

    /*
     * =====================================================
     * MEMBRESÍAS
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
            :company_id,
            'active'
        )
    ");

    $statement->execute([
        'user_id' => $alphaUserId,
        'company_id' => $alphaCompanyId,
    ]);

    $alphaMembershipId =
        (int) $pdo->lastInsertId();

    $statement->execute([
        'user_id' => $betaUserId,
        'company_id' => $betaCompanyId,
    ]);

    /*
     * =====================================================
     * ROLES TEMPORALES
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

    $createRole = static function (int $companyId, string $prefix) use ($pdo, $statement, &$roleIds): int {
        $suffix = bin2hex(
            random_bytes(6)
        );

        $statement->execute([
            'company_id' => $companyId,
            'name' => $prefix . ' ' . $suffix,
            'slug' =>
                strtolower($prefix)
                . '-'
                . $suffix,
        ]);

        $roleId =
            (int) $pdo->lastInsertId();

        $roleIds[] = $roleId;

        return $roleId;
    };

    $allRoleId = $createRole(
        $alphaCompanyId,
        'Controller-All'
    );

    $selectedRoleId = $createRole(
        $alphaCompanyId,
        'Controller-Selected'
    );

    $emptySelectedRoleId = $createRole(
        $alphaCompanyId,
        'Controller-Empty'
    );

    $duplicateRoleId = $createRole(
        $alphaCompanyId,
        'Controller-Duplicate'
    );

    $invalidScopeRoleId = $createRole(
        $alphaCompanyId,
        'Controller-Scope'
    );

    $foreignBranchRoleId = $createRole(
        $alphaCompanyId,
        'Controller-Foreign-Branch'
    );

    $betaRoleId = $createRole(
        $betaCompanyId,
        'Controller-Beta'
    );

    $controller =
        new UserController();

    $alphaContext =
        new CompanyContext(
            1,
            $alphaCompanyId,
            null
        );

    CompanyContextStore::set(
        $alphaContext
    );

    /*
     * =====================================================
     * 1. ASIGNACIÓN ALL
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $allRoleId,
            'branch_scope' => 'all',
            'branch_ids' => [
                $alphaBranches['CENTRO'],
                $alphaBranches['NORTE'],
            ],
        ]
    );

    checkUserRoleAssignmentController(
        'Asignación all no devuelve error HTTP',
        $result['status'] === 302
    );

    $statement = $pdo->prepare("
        SELECT
            id,
            branch_scope
        FROM user_company_roles
        WHERE user_company_id = :membership_id
          AND role_id = :role_id
        LIMIT 1
    ");

    $statement->execute([
        'membership_id' => $alphaMembershipId,
        'role_id' => $allRoleId,
    ]);

    $allAssignment =
        $statement->fetch();

    checkUserRoleAssignmentController(
        'Controlador asigna rol a la membresía Alpha correcta',
        $allAssignment !== false
        && $allAssignment['branch_scope'] === 'all'
    );

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_company_branches
        WHERE user_company_role_id = :assignment_id
    ");

    $statement->execute([
        'assignment_id' =>
            (int) $allAssignment['id'],
    ]);

    checkUserRoleAssignmentController(
        'Alcance all no persiste sucursales individuales',
        (int) $statement->fetchColumn() === 0
    );

    /*
     * =====================================================
     * 2. SELECTED
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $selectedRoleId,
            'branch_scope' => 'selected',
            'branch_ids' => [
                $alphaBranches['CENTRO'],
                $alphaBranches['NORTE'],
                $alphaBranches['CENTRO'],
            ],
        ]
    );

    checkUserRoleAssignmentController(
        'Asignación selected no devuelve error HTTP',
        $result['status'] === 302
    );

    $statement = $pdo->prepare("
        SELECT id
        FROM user_company_roles
        WHERE user_company_id = :membership_id
          AND role_id = :role_id
        LIMIT 1
    ");

    $statement->execute([
        'membership_id' => $alphaMembershipId,
        'role_id' => $selectedRoleId,
    ]);

    $selectedAssignmentId =
        (int) $statement->fetchColumn();

    $statement = $pdo->prepare("
        SELECT branch_id
        FROM user_company_branches
        WHERE user_company_role_id = :assignment_id
        ORDER BY branch_id
    ");

    $statement->execute([
        'assignment_id' =>
            $selectedAssignmentId,
    ]);

    $storedBranchIds = array_map(
        'intval',
        $statement->fetchAll(
            PDO::FETCH_COLUMN
        )
    );

    $expectedBranchIds = [
        $alphaBranches['CENTRO'],
        $alphaBranches['NORTE'],
    ];

    sort($storedBranchIds);
    sort($expectedBranchIds);

    checkUserRoleAssignmentController(
        'Selected conserva únicamente sucursales válidas sin duplicados',
        $storedBranchIds === $expectedBranchIds
    );

    /*
     * =====================================================
     * 3. SELECTED VACÍO
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' =>
                $emptySelectedRoleId,
            'branch_scope' => 'selected',
            'branch_ids' => [],
        ]
    );

    $statement = $pdo->prepare("
        SELECT
            id,
            branch_scope
        FROM user_company_roles
        WHERE user_company_id = :membership_id
          AND role_id = :role_id
        LIMIT 1
    ");

    $statement->execute([
        'membership_id' =>
            $alphaMembershipId,
        'role_id' =>
            $emptySelectedRoleId,
    ]);

    $emptyAssignment =
        $statement->fetch();

    checkUserRoleAssignmentController(
        'Selected vacío conserva alcance selected',
        $result['status'] === 302
        && $emptyAssignment !== false
        && $emptyAssignment['branch_scope']
        === 'selected'
    );

    /*
     * =====================================================
     * 4. ID INVÁLIDO
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        'abc',
        []
    );

    checkUserRoleAssignmentController(
        'ID no numérico devuelve 404',
        $result['status'] === 404
        && $result['output']
        === 'Usuario no encontrado.'
    );

    /*
     * =====================================================
     * 5. USUARIO DE OTRA EMPRESA
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $betaUserId,
        [
            'role_id' => $allRoleId,
            'branch_scope' => 'all',
        ]
    );

    checkUserRoleAssignmentController(
        'Alpha no puede asignar rol a usuario exclusivo de Beta',
        $result['status'] === 404
        && $result['output']
        === 'Usuario no encontrado.'
    );

    /*
     * =====================================================
     * 6. ROL DE OTRA EMPRESA
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $betaRoleId,
            'branch_scope' => 'all',
        ]
    );

    checkUserRoleAssignmentController(
        'Rol Beta desde contexto Alpha devuelve 422',
        $result['status'] === 422
    );

    /*
     * =====================================================
     * 7. ROL GLOBAL
     * =====================================================
     */

    $statement = $pdo->query("
        SELECT id
        FROM roles
        WHERE company_id IS NULL
          AND status = 'active'
        ORDER BY id
        LIMIT 1
    ");

    $globalRoleId =
        $statement->fetchColumn();

    if ($globalRoleId === false) {
        throw new RuntimeException(
            'No existe un rol global activo para la prueba.'
        );
    }

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' =>
                (int) $globalRoleId,
            'branch_scope' => 'all',
        ]
    );

    checkUserRoleAssignmentController(
        'Rol global no puede asignarse como rol empresarial',
        $result['status'] === 422
    );

    /*
     * =====================================================
     * 8. DUPLICADO
     * =====================================================
     */

    executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $duplicateRoleId,
            'branch_scope' => 'all',
        ]
    );

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $duplicateRoleId,
            'branch_scope' => 'all',
        ]
    );

    checkUserRoleAssignmentController(
        'Rol empresarial duplicado devuelve 409',
        $result['status'] === 409
    );

    /*
     * =====================================================
     * 9. SUCURSAL DE OTRA EMPRESA
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' =>
                $foreignBranchRoleId,
            'branch_scope' => 'selected',
            'branch_ids' => [
                $betaBranchId,
            ],
        ]
    );

    checkUserRoleAssignmentController(
        'Sucursal Beta desde Alpha devuelve 422',
        $result['status'] === 422
    );

    /*
     * Confirmamos que el fallo anterior no creó
     * una asignación parcial.
     */

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_company_roles
        WHERE user_company_id = :membership_id
          AND role_id = :role_id
    ");

    $statement->execute([
        'membership_id' =>
            $alphaMembershipId,
        'role_id' =>
            $foreignBranchRoleId,
    ]);

    checkUserRoleAssignmentController(
        'Sucursal extranjera no deja asignación parcial',
        (int) $statement->fetchColumn() === 0
    );

    /*
     * =====================================================
     * 10. ROLE_ID HTTP INVÁLIDO
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => 'abc',
            'branch_scope' => 'all',
        ]
    );

    checkUserRoleAssignmentController(
        'role_id HTTP inválido devuelve 422',
        $result['status'] === 422
    );

    /*
     * =====================================================
     * 11. BRANCH_IDS HTTP INVÁLIDO
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $allRoleId,
            'branch_scope' => 'selected',
            'branch_ids' => '1',
        ]
    );

    checkUserRoleAssignmentController(
        'branch_ids no array devuelve 422',
        $result['status'] === 422
    );

    /*
     * =====================================================
     * 12. BRANCH_ID INDIVIDUAL INVÁLIDO
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $allRoleId,
            'branch_scope' => 'selected',
            'branch_ids' => [
                'abc',
            ],
        ]
    );

    checkUserRoleAssignmentController(
        'branch_ids con valor inválido devuelve 422',
        $result['status'] === 422
    );

    /*
     * =====================================================
     * 13. BRANCH_SCOPE INVÁLIDO
     * =====================================================
     */

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' =>
                $invalidScopeRoleId,
            'branch_scope' => 'invalid',
            'branch_ids' => [],
        ]
    );

    checkUserRoleAssignmentController(
        'branch_scope inválido devuelve 422',
        $result['status'] === 422
    );

    /*
     * =====================================================
     * 14. SIN CONTEXTO
     * =====================================================
     */

    CompanyContextStore::clear();

    $result = executeUserRoleAssignment(
        $controller,
        (string) $alphaUserId,
        [
            'role_id' => $allRoleId,
            'branch_scope' => 'all',
        ]
    );

    checkUserRoleAssignmentController(
        'Sin contexto empresarial devuelve 403',
        $result['status'] === 403
    );

} catch (Throwable $exception) {
    $failed++;

    echo '[ERROR] '
        . $exception->getMessage()
        . PHP_EOL;
} finally {
    CompanyContextStore::clear();

    $_POST = [];

    /*
     * =====================================================
     * CLEANUP
     * =====================================================
     */

    if (!empty($userIds)) {
        try {
            $pdo->beginTransaction();

            $placeholders = [];
            $parameters = [];

            foreach (
                $userIds
                as $index => $userId
            ) {
                $parameter =
                    'user_' . $index;

                $placeholders[] =
                    ':' . $parameter;

                $parameters[$parameter] =
                    $userId;
            }

            $userPlaceholderSql =
                implode(
                    ', ',
                    $placeholders
                );

            $statement = $pdo->prepare("
                DELETE ucb
                FROM user_company_branches ucb

                INNER JOIN user_company_roles ucr
                    ON ucr.id =
                        ucb.user_company_role_id

                INNER JOIN user_companies uc
                    ON uc.id =
                        ucr.user_company_id

                WHERE uc.user_id IN (
                    {$userPlaceholderSql}
                )
            ");

            $statement->execute(
                $parameters
            );

            $statement = $pdo->prepare("
                DELETE ucr
                FROM user_company_roles ucr

                INNER JOIN user_companies uc
                    ON uc.id =
                        ucr.user_company_id

                WHERE uc.user_id IN (
                    {$userPlaceholderSql}
                )
            ");

            $statement->execute(
                $parameters
            );

            $statement = $pdo->prepare("
                DELETE FROM user_companies
                WHERE user_id IN (
                    {$userPlaceholderSql}
                )
            ");

            $statement->execute(
                $parameters
            );

            $statement = $pdo->prepare("
                DELETE FROM users
                WHERE id IN (
                    {$userPlaceholderSql}
                )
            ");

            $statement->execute(
                $parameters
            );

            if (!empty($roleIds)) {
                $rolePlaceholders = [];
                $roleParameters = [];

                foreach (
                    $roleIds
                    as $index => $roleId
                ) {
                    $parameter =
                        'role_' . $index;

                    $rolePlaceholders[] =
                        ':' . $parameter;

                    $roleParameters[$parameter] =
                        $roleId;
                }

                $statement = $pdo->prepare("
                    DELETE FROM roles
                    WHERE id IN (
                        " . implode(
                    ', ',
                    $rolePlaceholders
                ) . "
                    )
                ");

                $statement->execute(
                    $roleParameters
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

$output = ob_get_clean();

echo $output;

exit(
    $failed === 0
    ? 0
    : 1
);
