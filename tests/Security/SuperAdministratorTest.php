<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Development\AuthorizationTestSeeder;
use NovaSysCore\Security\AuthorizationService;

$pdo = Database::connection();

(new AuthorizationTestSeeder())->run();

function getSuperAdminTestId(
    \PDO $pdo,
    string $sql,
    array $parameters,
    string $errorMessage
): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    $id = $statement->fetchColumn();

    if ($id === false) {
        throw new \RuntimeException($errorMessage);
    }

    return (int) $id;
}

$userId = getSuperAdminTestId(
    $pdo,
    "
        SELECT id
        FROM users
        WHERE email = :email
        LIMIT 1
    ",
    [
        'email' => 'authorization.test@novasyscore.local',
    ],
    'No se encontró el usuario de prueba.'
);

$superAdministratorRoleId = getSuperAdminTestId(
    $pdo,
    "
        SELECT id
        FROM roles
        WHERE company_id IS NULL
          AND slug = :slug
          AND is_system = TRUE
          AND status = 'active'
        LIMIT 1
    ",
    [
        'slug' => 'super-administrator',
    ],
    'No se encontró el rol Super Administrator.'
);

$companyBetaId = getSuperAdminTestId(
    $pdo,
    "
        SELECT id
        FROM companies
        WHERE slug = :slug
        LIMIT 1
    ",
    [
        'slug' => 'empresa-beta',
    ],
    'No se encontró Empresa Beta.'
);

$branchBetaId = getSuperAdminTestId(
    $pdo,
    "
        SELECT id
        FROM branches
        WHERE company_id = :company_id
          AND slug = :slug
        LIMIT 1
    ",
    [
        'company_id' => $companyBetaId,
        'slug' => 'sucursal-beta',
    ],
    'No se encontró Sucursal Beta.'
);

/*
 * Limpiamos cualquier rol global previo.
 */

$statement = $pdo->prepare("
    DELETE FROM user_system_roles
    WHERE user_id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

/*
 * Asignamos exclusivamente Super Administrator.
 */

$statement = $pdo->prepare("
    INSERT INTO user_system_roles (
        user_id,
        role_id,
        created_at,
        updated_at
    )
    VALUES (
        :user_id,
        :role_id,
        NOW(),
        NOW()
    )
");

$statement->execute([
    'user_id' => $userId,
    'role_id' => $superAdministratorRoleId,
]);

/*
 * Aseguramos usuario activo.
 */

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

$authorization = new AuthorizationService();

$tests = [];

/*
 * =========================================================
 * SUPER ADMINISTRATOR ACTIVO
 * =========================================================
 */

$tests[] = [
    'name' => 'Super Admin + permiso existente',
    'expected' => true,
    'result' => $authorization->canSystem(
        $userId,
        'users.view'
    ),
];

$tests[] = [
    'name' => 'Super Admin + permiso inexistente',
    'expected' => true,
    'result' => $authorization->canSystem(
        $userId,
        'core.permission.future'
    ),
];

$tests[] = [
    'name' => 'Super Admin bypass Empresa Beta',
    'expected' => true,
    'result' => $authorization->can(
        $userId,
        $companyBetaId,
        'users.view'
    ),
];

$tests[] = [
    'name' => 'Super Admin + permiso futuro empresa',
    'expected' => true,
    'result' => $authorization->can(
        $userId,
        $companyBetaId,
        'future.permission'
    ),
];

$tests[] = [
    'name' => 'Super Admin + sucursal Beta',
    'expected' => true,
    'result' => $authorization->can(
        $userId,
        $companyBetaId,
        'users.view',
        $branchBetaId
    ),
];

$tests[] = [
    'name' => 'Super Admin + empresa inexistente',
    'expected' => true,
    'result' => $authorization->can(
        $userId,
        999999,
        'users.view'
    ),
];

/*
 * =========================================================
 * SUPER ADMINISTRATOR INACTIVO
 * =========================================================
 */

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'inactive'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

$tests[] = [
    'name' => 'Super Admin inactivo + sistema',
    'expected' => false,
    'result' => $authorization->canSystem(
        $userId,
        'users.view'
    ),
];

$tests[] = [
    'name' => 'Super Admin inactivo + empresa',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyBetaId,
        'users.view'
    ),
];

/*
 * Restaurar usuario.
 */

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

/*
 * =========================================================
 * RESULTADOS
 * =========================================================
 */

echo PHP_EOL;
echo 'NovaSysCore - Super Administrator Test' . PHP_EOL;
echo str_repeat('=', 72) . PHP_EOL;

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $success = $test['result'] === $test['expected'];

    if ($success) {
        $passed++;
    } else {
        $failed++;
    }

    echo sprintf(
        "[%s] %-46s esperado=%s obtenido=%s",
        $success ? 'OK' : 'FAIL',
        $test['name'],
        $test['expected'] ? 'ALLOW' : 'DENY',
        $test['result'] ? 'ALLOW' : 'DENY'
    );

    echo PHP_EOL;
}

echo str_repeat('-', 72) . PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;
echo PHP_EOL;

exit($failed === 0 ? 0 : 1);