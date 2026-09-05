<?php

ob_start();

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Auth\AuthenticationService;
use NovaSysCore\Auth\SessionManager;
use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Development\AuthorizationTestSeeder;

$pdo = Database::connection();

$session = new SessionManager();
$session->start();

(new AuthorizationTestSeeder())->run();

ob_clean();

$email = 'authorization.test@novasyscore.local';
$password = 'Test1234!';
$wrongPassword = 'PasswordSecretoQueNoDebeAuditarse!';

$testIp = '127.0.30.1';

$originalRemoteAddress =
    $_SERVER['REMOTE_ADDR'] ?? null;

$_SERVER['REMOTE_ADDR'] = $testIp;

/*
 * =========================================================
 * PREPARAR USUARIO
 * =========================================================
 */

$statement = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = :email
    LIMIT 1
");

$statement->execute([
    'email' => $email,
]);

$userId = $statement->fetchColumn();

if ($userId === false) {
    throw new RuntimeException(
        'No se encontró el usuario de prueba.'
    );
}

$userId = (int) $userId;

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'active'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

/*
 * Limpiamos únicamente los datos pertenecientes
 * a este escenario de prueba.
 */
$statement = $pdo->prepare("
    DELETE FROM login_attempts
    WHERE ip_address = :ip_address
");

$statement->execute([
    'ip_address' => $testIp,
]);

$statement = $pdo->prepare("
    DELETE FROM audit_logs
    WHERE ip_address = :ip_address
");

$statement->execute([
    'ip_address' => $testIp,
]);

$auth = new AuthenticationService(
    $session
);

$tests = [];

/*
 * =========================================================
 * 1. LOGIN_FAILED
 * =========================================================
 */

$auth->attempt(
    $email,
    $wrongPassword
);

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM audit_logs
    WHERE action = 'LOGIN_FAILED'
      AND user_id = :user_id
      AND ip_address = :ip_address
");

$statement->execute([
    'user_id' => $userId,
    'ip_address' => $testIp,
]);

$tests[] = [
    'name' => 'Password incorrecto genera LOGIN_FAILED',
    'expected' => 1,
    'result' => (int) $statement->fetchColumn(),
];

/*
 * =========================================================
 * 2. LOGIN_SUCCESS
 * =========================================================
 */

$result = $auth->attempt(
    $email,
    $password
);

$tests[] = [
    'name' => 'Login valido sigue funcionando',
    'expected' => true,
    'result' => $result,
];

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM audit_logs
    WHERE action = 'LOGIN_SUCCESS'
      AND user_id = :user_id
      AND ip_address = :ip_address
");

$statement->execute([
    'user_id' => $userId,
    'ip_address' => $testIp,
]);

$tests[] = [
    'name' => 'Login valido genera LOGIN_SUCCESS',
    'expected' => 1,
    'result' => (int) $statement->fetchColumn(),
];

/*
 * =========================================================
 * 3. LOGOUT
 * =========================================================
 */

$auth->logout();

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM audit_logs
    WHERE action = 'LOGOUT'
      AND user_id = :user_id
      AND ip_address = :ip_address
");

$statement->execute([
    'user_id' => $userId,
    'ip_address' => $testIp,
]);

$tests[] = [
    'name' => 'Logout genera evento LOGOUT',
    'expected' => 1,
    'result' => (int) $statement->fetchColumn(),
];

/*
 * =========================================================
 * 4. LOGIN_BLOCKED
 * =========================================================
 *
 * Ya existe un fallo previo.
 * Agregamos cuatro más para llegar exactamente a cinco.
 */

for ($i = 0; $i < 4; $i++) {
    $auth->attempt(
        $email,
        $wrongPassword
    );
}

/*
 * Este sexto intento debe ser detenido por
 * LoginRateLimiter antes de comprobar contraseña.
 */
$blockedResult = $auth->attempt(
    $email,
    $password
);

$tests[] = [
    'name' => 'Rate limit rechaza intento posterior',
    'expected' => false,
    'result' => $blockedResult,
];

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM audit_logs
    WHERE action = 'LOGIN_BLOCKED'
      AND ip_address = :ip_address
");

$statement->execute([
    'ip_address' => $testIp,
]);

$tests[] = [
    'name' => 'Rate limit genera LOGIN_BLOCKED',
    'expected' => 1,
    'result' => (int) $statement->fetchColumn(),
];

/*
 * =========================================================
 * 5. CORREO INEXISTENTE
 * =========================================================
 *
 * Usamos otra IP para no quedar afectados por el
 * rate limit del escenario anterior.
 */

$unknownIp = '127.0.30.2';

$_SERVER['REMOTE_ADDR'] = $unknownIp;

$statement = $pdo->prepare("
    DELETE FROM login_attempts
    WHERE ip_address = :ip_address
");

$statement->execute([
    'ip_address' => $unknownIp,
]);

$statement = $pdo->prepare("
    DELETE FROM audit_logs
    WHERE ip_address = :ip_address
");

$statement->execute([
    'ip_address' => $unknownIp,
]);

$unknownEmail =
    'no.existe.audit@novasyscore.local';

$auth->attempt(
    $unknownEmail,
    $wrongPassword
);

$statement = $pdo->prepare("
    SELECT
        user_id,
        metadata
    FROM audit_logs
    WHERE action = 'LOGIN_FAILED'
      AND ip_address = :ip_address
    ORDER BY id DESC
    LIMIT 1
");

$statement->execute([
    'ip_address' => $unknownIp,
]);

$unknownAudit = $statement->fetch(
    PDO::FETCH_ASSOC
);

$tests[] = [
    'name' => 'Correo inexistente genera LOGIN_FAILED',
    'expected' => true,
    'result' => is_array($unknownAudit),
];

$tests[] = [
    'name' => 'Correo inexistente no inventa user_id',
    'expected' => null,
    'result' => is_array($unknownAudit)
        ? $unknownAudit['user_id']
        : 'missing',
];

$metadata = is_array($unknownAudit)
    ? json_decode(
        $unknownAudit['metadata'] ?? '',
        true
    )
    : null;

$tests[] = [
    'name' => 'Correo inexistente usa identifier_hash',
    'expected' => hash(
        'sha256',
        strtolower(trim($unknownEmail))
    ),
    'result' => is_array($metadata)
        ? ($metadata['identifier_hash'] ?? null)
        : null,
];

/*
 * =========================================================
 * 6. NO FILTRAR DATOS SENSIBLES
 * =========================================================
 */

$statement = $pdo->prepare("
    SELECT
        old_values,
        new_values,
        metadata
    FROM audit_logs
    WHERE ip_address IN (
        :test_ip,
        :unknown_ip
    )
");

$statement->execute([
    'test_ip' => $testIp,
    'unknown_ip' => $unknownIp,
]);

$rows = $statement->fetchAll(
    PDO::FETCH_ASSOC
);

$serializedAudit = json_encode(
    $rows,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
);

$containsWrongPassword =
    is_string($serializedAudit)
    && str_contains(
        $serializedAudit,
        $wrongPassword
    );

$containsCorrectPassword =
    is_string($serializedAudit)
    && str_contains(
        $serializedAudit,
        $password
    );

$containsUnknownEmail =
    is_string($serializedAudit)
    && str_contains(
        strtolower($serializedAudit),
        strtolower($unknownEmail)
    );

$tests[] = [
    'name' => 'Auditoria no guarda password incorrecto',
    'expected' => false,
    'result' => $containsWrongPassword,
];

$tests[] = [
    'name' => 'Auditoria no guarda password correcto',
    'expected' => false,
    'result' => $containsCorrectPassword,
];

$tests[] = [
    'name' => 'Auditoria no guarda email inexistente en claro',
    'expected' => false,
    'result' => $containsUnknownEmail,
];

/*
 * =========================================================
 * LIMPIEZA
 * =========================================================
 */

$cleanupIps = [
    $testIp,
    $unknownIp,
];

$placeholders = implode(
    ',',
    array_fill(
        0,
        count($cleanupIps),
        '?'
    )
);

$statement = $pdo->prepare("
    DELETE FROM login_attempts
    WHERE ip_address IN ({$placeholders})
");

$statement->execute($cleanupIps);

$statement = $pdo->prepare("
    DELETE FROM audit_logs
    WHERE ip_address IN ({$placeholders})
");

$statement->execute($cleanupIps);

if ($originalRemoteAddress === null) {
    unset($_SERVER['REMOTE_ADDR']);
} else {
    $_SERVER['REMOTE_ADDR']
        = $originalRemoteAddress;
}

/*
 * =========================================================
 * RESULTADOS
 * =========================================================
 */

echo PHP_EOL;
echo 'NovaSysCore - Authentication Audit Test'
    . PHP_EOL;

echo str_repeat('=', 78)
    . PHP_EOL;

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $success =
        $test['expected']
        === $test['result'];

    if ($success) {
        $passed++;
    } else {
        $failed++;
    }

    echo sprintf(
        "[%s] %-52s esperado=%s obtenido=%s",
        $success ? 'OK' : 'FAIL',
        $test['name'],
        var_export(
            $test['expected'],
            true
        ),
        var_export(
            $test['result'],
            true
        )
    );

    echo PHP_EOL;
}

echo str_repeat('-', 78)
    . PHP_EOL;

echo "Correctas: {$passed}"
    . PHP_EOL;

echo "Fallidas:  {$failed}"
    . PHP_EOL;

echo PHP_EOL;

ob_end_flush();

exit(
    $failed === 0
        ? 0
        : 1
);