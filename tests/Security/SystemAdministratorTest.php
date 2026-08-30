<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Development\AuthorizationTestSeeder;
use NovaSysCore\Security\AuthorizationService;

$pdo = Database::connection();

/*
 * =========================================================
 * PREPARAR ESCENARIO BASE
 * =========================================================
 */

(new AuthorizationTestSeeder())->run();

function getTestId(
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

/*
 * =========================================================
 * LOCALIZAR USUARIO
 * =========================================================
 */

$userId = getTestId(
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

/*
 * =========================================================
 * LOCALIZAR SYSTEM ADMINISTRATOR
 * =========================================================
 */

$systemAdministratorRoleId = getTestId(
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
        'slug' => 'system-administrator',
    ],
    'No se encontró el rol System Administrator.'
);

/*
 * =========================================================
 * LOCALIZAR EMPRESA BETA
 *
 * El usuario de prueba no tiene membresía en esta empresa.
 * =========================================================
 */

$companyBetaId = getTestId(
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

/*
 * =========================================================
 * AISLAR ROLES GLOBALES
 *
 * Eliminamos cualquier asignación global anterior para que
 * Super Administrator no contamine esta prueba.
 * =========================================================
 */

$statement = $pdo->prepare("
    DELETE FROM user_system_roles
    WHERE user_id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

/*
 * Asignamos exclusivamente System Administrator.
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
    'role_id' => $systemAdministratorRoleId,
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

/*
 * =========================================================
 * ESCENARIO 1: SYSTEM ADMINISTRATOR ACTIVO
 * =========================================================
 */

$tests = [];

$tests[] = [
    'name' => 'System Admin + users.view',
    'expected' => true,
    'result' => $authorization->canSystem(
        $userId,
        'users.view'
    ),
];

$tests[] = [
    'name' => 'System Admin + roles.assign',
    'expected' => true,
    'result' => $authorization->canSystem(
        $userId,
        'roles.assign'
    ),
];

$tests[] = [
    'name' => 'System Admin + permiso inexistente',
    'expected' => false,
    'result' => $authorization->canSystem(
        $userId,
        'core.permission.does-not-exist'
    ),
];

$tests[] = [
    'name' => 'System Admin NO bypass Empresa Beta',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyBetaId,
        'users.view'
    ),
];

/*
 * =========================================================
 * ESCENARIO 2: SYSTEM ADMINISTRATOR INACTIVO
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
    'name' => 'System Admin inactivo + users.view',
    'expected' => false,
    'result' => $authorization->canSystem(
        $userId,
        'users.view'
    ),
];

/*
 * =========================================================
 * RESTAURAR USUARIO
 *
 * La prueba no debe dejar al usuario ficticio inactivo.
 * =========================================================
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
echo 'NovaSysCore - System Administrator Test' . PHP_EOL;
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