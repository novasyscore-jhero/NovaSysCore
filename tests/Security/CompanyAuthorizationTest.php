<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Development\AuthorizationTestSeeder;
use NovaSysCore\Security\AuthorizationService;

$pdo = Database::connection();

/*
 * =========================================================
 * PREPARAR ESCENARIO
 * =========================================================
 */

(new AuthorizationTestSeeder())->run();

/*
 * Localizamos los IDs dinámicamente.
 * No dependemos de que sean 1, 2, 3...
 */

function getId(
    PDO $pdo,
    string $sql,
    array $parameters,
    string $errorMessage
): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    $id = $statement->fetchColumn();

    if ($id === false) {
        throw new RuntimeException($errorMessage);
    }

    return (int) $id;
}

$userId = getId(
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

$companyAlphaId = getId(
    $pdo,
    "
        SELECT id
        FROM companies
        WHERE slug = :slug
        LIMIT 1
    ",
    [
        'slug' => 'empresa-alpha',
    ],
    'No se encontró Empresa Alpha.'
);

$companyBetaId = getId(
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

$branchCentroId = getId(
    $pdo,
    "
        SELECT id
        FROM branches
        WHERE company_id = :company_id
          AND slug = :slug
        LIMIT 1
    ",
    [
        'company_id' => $companyAlphaId,
        'slug' => 'sucursal-centro',
    ],
    'No se encontró Sucursal Centro.'
);

$branchNorteId = getId(
    $pdo,
    "
        SELECT id
        FROM branches
        WHERE company_id = :company_id
          AND slug = :slug
        LIMIT 1
    ",
    [
        'company_id' => $companyAlphaId,
        'slug' => 'sucursal-norte',
    ],
    'No se encontró Sucursal Norte.'
);

$branchBetaId = getId(
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
 * =========================================================
 * AISLAR LA PRUEBA EMPRESARIAL
 *
 * Eliminamos únicamente roles globales del usuario ficticio.
 * Así Super Administrator/System Administrator no pueden
 * alterar los resultados de esta batería.
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
 * Aseguramos que el usuario esté activo.
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
 * ESCENARIO 1
 * branch_scope = selected
 * =========================================================
 */

$statement = $pdo->prepare("
    UPDATE user_company_roles ucr

    INNER JOIN user_companies uc
        ON uc.id = ucr.user_company_id

    INNER JOIN roles r
        ON r.id = ucr.role_id

    SET ucr.branch_scope = 'selected'

    WHERE uc.user_id = :user_id
      AND uc.company_id = :company_id
      AND r.company_id = :company_id_role
      AND r.slug = 'administrador-alpha'
");

$statement->execute([
    'user_id' => $userId,
    'company_id' => $companyAlphaId,
    'company_id_role' => $companyAlphaId,
]);

$tests[] = [
    'name' => 'Selected + Centro + users.view',
    'expected' => true,
    'result' => $authorization->can(
        $userId,
        $companyAlphaId,
        'users.view',
        $branchCentroId
    ),
];

$tests[] = [
    'name' => 'Selected + Norte + users.view',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyAlphaId,
        'users.view',
        $branchNorteId
    ),
];

$tests[] = [
    'name' => 'Selected + permiso no asignado',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyAlphaId,
        'users.update',
        $branchCentroId
    ),
];

$tests[] = [
    'name' => 'Selected + Empresa Beta',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyBetaId,
        'users.view'
    ),
];

$tests[] = [
    'name' => 'Sucursal Beta usando Empresa Alpha',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyAlphaId,
        'users.view',
        $branchBetaId
    ),
];

/*
 * =========================================================
 * ESCENARIO 2
 * branch_scope = all
 * =========================================================
 */

$statement = $pdo->prepare("
    UPDATE user_company_roles ucr

    INNER JOIN user_companies uc
        ON uc.id = ucr.user_company_id

    INNER JOIN roles r
        ON r.id = ucr.role_id

    SET ucr.branch_scope = 'all'

    WHERE uc.user_id = :user_id
      AND uc.company_id = :company_id
      AND r.company_id = :company_id_role
      AND r.slug = 'administrador-alpha'
");

$statement->execute([
    'user_id' => $userId,
    'company_id' => $companyAlphaId,
    'company_id_role' => $companyAlphaId,
]);

$tests[] = [
    'name' => 'All + Centro + users.view',
    'expected' => true,
    'result' => $authorization->can(
        $userId,
        $companyAlphaId,
        'users.view',
        $branchCentroId
    ),
];

$tests[] = [
    'name' => 'All + Norte + users.view',
    'expected' => true,
    'result' => $authorization->can(
        $userId,
        $companyAlphaId,
        'users.view',
        $branchNorteId
    ),
];

$tests[] = [
    'name' => 'All + Empresa Beta',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyBetaId,
        'users.view'
    ),
];

$tests[] = [
    'name' => 'All + sucursal cruzada',
    'expected' => false,
    'result' => $authorization->can(
        $userId,
        $companyAlphaId,
        'users.view',
        $branchBetaId
    ),
];

/*
 * =========================================================
 * RESULTADOS
 * =========================================================
 */

echo PHP_EOL;
echo 'NovaSysCore - Company Authorization Test' . PHP_EOL;
echo str_repeat('=', 68) . PHP_EOL;

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
        "[%s] %-42s esperado=%s obtenido=%s",
        $success ? 'OK' : 'FAIL',
        $test['name'],
        $test['expected'] ? 'ALLOW' : 'DENY',
        $test['result'] ? 'ALLOW' : 'DENY'
    );

    echo PHP_EOL;
}

echo str_repeat('-', 68) . PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;
echo PHP_EOL;

exit($failed === 0 ? 0 : 1);