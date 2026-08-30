<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Development\AuthorizationTestSeeder;
use NovaSysCore\Security\SystemRoleAssignmentService;

$pdo = Database::connection();

(new AuthorizationTestSeeder())->run();

function getSystemRoleTestId(
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

function expectSuccess(
    string $name,
    callable $callback
): array {
    try {
        $callback();

        return [
            'name' => $name,
            'expected' => 'SUCCESS',
            'result' => 'SUCCESS',
        ];
    } catch (\Throwable $exception) {
        return [
            'name' => $name,
            'expected' => 'SUCCESS',
            'result' => 'DENY',
            'message' => $exception->getMessage(),
        ];
    }
}

function expectDenied(
    string $name,
    callable $callback
): array {
    try {
        $callback();

        return [
            'name' => $name,
            'expected' => 'DENY',
            'result' => 'SUCCESS',
        ];
    } catch (\RuntimeException $exception) {
        return [
            'name' => $name,
            'expected' => 'DENY',
            'result' => 'DENY',
        ];
    }
}

/*
 * =========================================================
 * IDENTIFICAR DATOS DE PRUEBA
 * =========================================================
 */

$testUserId = getSystemRoleTestId(
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

$superAdminRoleId = getSystemRoleTestId(
    $pdo,
    "
        SELECT id
        FROM roles
        WHERE company_id IS NULL
          AND slug = 'super-administrator'
          AND is_system = TRUE
          AND status = 'active'
        LIMIT 1
    ",
    [],
    'No se encontró Super Administrator.'
);

$systemAdminRoleId = getSystemRoleTestId(
    $pdo,
    "
        SELECT id
        FROM roles
        WHERE company_id IS NULL
          AND slug = 'system-administrator'
          AND is_system = TRUE
          AND status = 'active'
        LIMIT 1
    ",
    [],
    'No se encontró System Administrator.'
);

$companyRoleId = getSystemRoleTestId(
    $pdo,
    "
        SELECT id
        FROM roles
        WHERE company_id IS NOT NULL
          AND slug = 'administrador-alpha'
        LIMIT 1
    ",
    [],
    'No se encontró el rol empresarial de prueba.'
);

/*
 * =========================================================
 * CREAR SEGUNDO USUARIO FICTICIO
 * =========================================================
 */

$targetEmail = 'system.role.target@novasyscore.local';

$statement = $pdo->prepare("
    INSERT INTO users (
        name,
        last_name,
        display_name,
        email,
        password_hash,
        status,
        created_at,
        updated_at
    )
    VALUES (
        'System',
        'Role Target',
        'System Role Target',
        :email,
        :password_hash,
        'active',
        NOW(),
        NOW()
    )
    ON DUPLICATE KEY UPDATE
        status = 'active',
        updated_at = NOW()
");

$statement->execute([
    'email' => $targetEmail,
    'password_hash' => password_hash(
        'Test1234!',
        PASSWORD_DEFAULT
    ),
]);

$targetUserId = getSystemRoleTestId(
    $pdo,
    "
        SELECT id
        FROM users
        WHERE email = :email
        LIMIT 1
    ",
    [
        'email' => $targetEmail,
    ],
    'No se pudo crear el usuario objetivo.'
);

/*
 * =========================================================
 * LIMPIAR ROLES GLOBALES DE AMBOS USUARIOS
 * =========================================================
 */

$statement = $pdo->prepare("
    DELETE FROM user_system_roles
    WHERE user_id IN (:actor_id, :target_id)
");

$statement->execute([
    'actor_id' => $testUserId,
    'target_id' => $targetUserId,
]);

/*
 * Convertimos al usuario principal en Super Administrator
 * directamente para preparar el laboratorio.
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
    'user_id' => $testUserId,
    'role_id' => $superAdminRoleId,
]);

$service = new SystemRoleAssignmentService();

$tests = [];

/*
 * =========================================================
 * 1. SUPER ADMIN ASIGNA SYSTEM ADMIN
 * =========================================================
 */

$tests[] = expectSuccess(
    'Super Admin asigna System Admin',
    function () use (
        $service,
        $testUserId,
        $targetUserId,
        $systemAdminRoleId
    ): void {
        $service->assignSystemRole(
            $testUserId,
            $targetUserId,
            $systemAdminRoleId
        );
    }
);

/*
 * =========================================================
 * 2. ASIGNACIÓN REPETIDA DEBE SER IDEMPOTENTE
 * =========================================================
 */

$tests[] = expectSuccess(
    'Asignación repetida es idempotente',
    function () use (
        $service,
        $testUserId,
        $targetUserId,
        $systemAdminRoleId
    ): void {
        $service->assignSystemRole(
            $testUserId,
            $targetUserId,
            $systemAdminRoleId
        );
    }
);

/*
 * =========================================================
 * 3. SYSTEM ADMIN NO PUEDE ESCALAR A SUPER ADMIN
 *
 * El usuario objetivo ya es System Administrator.
 * Intentará asignarse Super Administrator.
 * =========================================================
 */

$tests[] = expectDenied(
    'System Admin no puede escalar a Super Admin',
    function () use (
        $service,
        $targetUserId,
        $superAdminRoleId
    ): void {
        $service->assignSystemRole(
            $targetUserId,
            $targetUserId,
            $superAdminRoleId
        );
    }
);

/*
 * =========================================================
 * 4. NO SE PUEDE USAR UN ROL EMPRESARIAL
 * =========================================================
 */

$tests[] = expectDenied(
    'Rechaza rol empresarial como rol global',
    function () use (
        $service,
        $testUserId,
        $targetUserId,
        $companyRoleId
    ): void {
        $service->assignSystemRole(
            $testUserId,
            $targetUserId,
            $companyRoleId
        );
    }
);

/*
 * =========================================================
 * 5. SUPER ADMIN RETIRA SYSTEM ADMIN
 * =========================================================
 */

$tests[] = expectSuccess(
    'Super Admin retira System Admin',
    function () use (
        $service,
        $testUserId,
        $targetUserId,
        $systemAdminRoleId
    ): void {
        $service->removeSystemRole(
            $testUserId,
            $targetUserId,
            $systemAdminRoleId
        );
    }
);

/*
 * =========================================================
 * 6. SUPER ADMIN NO PUEDE QUITARSE SU PROPIO SUPER ADMIN
 * =========================================================
 */

$tests[] = expectDenied(
    'Super Admin no puede retirar su propio rol',
    function () use (
        $service,
        $testUserId,
        $superAdminRoleId
    ): void {
        $service->removeSystemRole(
            $testUserId,
            $testUserId,
            $superAdminRoleId
        );
    }
);

/*
 * =========================================================
 * 7. ACTOR INACTIVO NO PUEDE ASIGNAR
 * =========================================================
 */

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'inactive'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $testUserId,
]);

$tests[] = expectDenied(
    'Super Admin inactivo no puede asignar',
    function () use (
        $service,
        $testUserId,
        $targetUserId,
        $systemAdminRoleId
    ): void {
        $service->assignSystemRole(
            $testUserId,
            $targetUserId,
            $systemAdminRoleId
        );
    }
);

/*
 * Restauramos actor.
 */

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $testUserId,
]);

/*
 * =========================================================
 * 8. TARGET INACTIVO NO PUEDE RECIBIR ROL
 * =========================================================
 */

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'inactive'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $targetUserId,
]);

$tests[] = expectDenied(
    'Usuario inactivo no puede recibir rol global',
    function () use (
        $service,
        $testUserId,
        $targetUserId,
        $systemAdminRoleId
    ): void {
        $service->assignSystemRole(
            $testUserId,
            $targetUserId,
            $systemAdminRoleId
        );
    }
);

/*
 * Restauramos target.
 */

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $targetUserId,
]);

/*
 * =========================================================
 * RESULTADOS
 * =========================================================
 */

echo PHP_EOL;
echo 'NovaSysCore - System Role Assignment Service Test' . PHP_EOL;
echo str_repeat('=', 76) . PHP_EOL;

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $success = $test['expected'] === $test['result'];

    if ($success) {
        $passed++;
    } else {
        $failed++;
    }

    echo sprintf(
        "[%s] %-48s esperado=%s obtenido=%s",
        $success ? 'OK' : 'FAIL',
        $test['name'],
        $test['expected'],
        $test['result']
    );

    echo PHP_EOL;

    if (!$success && isset($test['message'])) {
        echo '       ' . $test['message'] . PHP_EOL;
    }
}

echo str_repeat('-', 76) . PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;
echo PHP_EOL;

exit($failed === 0 ? 0 : 1);