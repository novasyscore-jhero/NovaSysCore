<?php

/*
 * Mantenemos la salida en buffer porque las pruebas de sesión
 * necesitan poder regenerar IDs y manipular cookies sin que PHP
 * considere que los headers ya fueron enviados.
 */
ob_start();

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use NovaSysCore\Auth\Auth;
use NovaSysCore\Auth\AuthenticationService;
use NovaSysCore\Auth\SessionManager;
use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Development\AuthorizationTestSeeder;

$pdo = Database::connection();

$session = new SessionManager();

/*
 * Iniciamos la sesión antes de que cualquier seeder pueda imprimir
 * información en consola.
 */
$session->start();

/*
 * Preparamos el usuario conocido del laboratorio.
 */
(new AuthorizationTestSeeder())->run();

/*
 * Eliminamos del resultado visual la salida informativa del seeder.
 */
ob_clean();

/*
 * Conservamos la IP original del entorno para restaurarla
 * al terminar las pruebas.
 */
$originalRemoteAddress = $_SERVER['REMOTE_ADDR']
    ?? null;

/*
 * IPs reservadas exclusivamente para este laboratorio.
 */
$normalTestIp = '127.0.20.1';
$pairRateLimitIp = '127.0.20.2';
$globalRateLimitIp = '127.0.20.3';

$testIps = [
    $normalTestIp,
    $pairRateLimitIp,
    $globalRateLimitIp,
];

/*
 * =========================================================
 * FUNCIONES AUXILIARES
 * =========================================================
 */

function authTestUserId(
    \PDO $pdo,
    string $email
): int {
    $statement = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

    $statement->execute([
        'email' => $email,
    ]);

    $id = $statement->fetchColumn();

    if ($id === false) {
        throw new \RuntimeException(
            'No se encontró el usuario de prueba.'
        );
    }

    return (int) $id;
}

function cleanAuthenticationAttempts(
    \PDO $pdo,
    array $ips
): void {
    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($ips),
            '?'
        )
    );

    $statement = $pdo->prepare("
        DELETE FROM login_attempts
        WHERE ip_address IN ({$placeholders})
    ");

    $statement->execute($ips);
}

/*
 * =========================================================
 * PREPARAR LABORATORIO
 * =========================================================
 */

cleanAuthenticationAttempts(
    $pdo,
    $testIps
);

$email = 'authorization.test@novasyscore.local';
$password = 'Test1234!';

$userId = authTestUserId(
    $pdo,
    $email
);

/*
 * Dejamos el usuario en estado conocido.
 */
$statement = $pdo->prepare("
    UPDATE users
    SET
        status = 'active',
        last_login_at = NULL
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

/*
 * Las primeras pruebas utilizan una IP propia para que
 * los fallos normales no interfieran con las pruebas
 * específicas del rate limiter.
 */
$_SERVER['REMOTE_ADDR'] = $normalTestIp;

$auth = new AuthenticationService($session);

$tests = [];

/*
 * =========================================================
 * 1. PASSWORD INCORRECTO
 * =========================================================
 */

$result = $auth->attempt(
    $email,
    'PasswordIncorrecto!'
);

$tests[] = [
    'name' => 'Password incorrecto',
    'expected' => false,
    'result' => $result,
];

/*
 * =========================================================
 * 2. USUARIO INEXISTENTE
 * =========================================================
 */

$result = $auth->attempt(
    'usuario.inexistente@novasyscore.local',
    $password
);

$tests[] = [
    'name' => 'Usuario inexistente',
    'expected' => false,
    'result' => $result,
];

/*
 * =========================================================
 * 3. PASSWORD VACÍO
 * =========================================================
 */

$result = $auth->attempt(
    $email,
    ''
);

$tests[] = [
    'name' => 'Password vacio',
    'expected' => false,
    'result' => $result,
];

/*
 * =========================================================
 * 4. NORMALIZACIÓN DE EMAIL
 * =========================================================
 */

$result = $auth->attempt(
    '   AUTHORIZATION.TEST@NOVASYSCORE.LOCAL   ',
    $password
);

$tests[] = [
    'name' => 'Login con email normalizado',
    'expected' => true,
    'result' => $result,
];

/*
 * =========================================================
 * 5. SESIÓN CREADA
 * =========================================================
 */

$tests[] = [
    'name' => 'SessionManager reconoce usuario',
    'expected' => true,
    'result' => $session->check(),
];

$tests[] = [
    'name' => 'SessionManager conserva user ID',
    'expected' => $userId,
    'result' => $session->userId(),
];

/*
 * =========================================================
 * 6. AUTH FACADE
 * =========================================================
 */

$tests[] = [
    'name' => 'Auth::check despues de login',
    'expected' => true,
    'result' => Auth::check(),
];

$tests[] = [
    'name' => 'Auth::id devuelve usuario',
    'expected' => $userId,
    'result' => Auth::id(),
];

$user = Auth::user();

$tests[] = [
    'name' => 'Auth::user carga usuario activo',
    'expected' => true,
    'result' => is_array($user)
        && (int) ($user['id'] ?? 0) === $userId
        && ($user['email'] ?? null) === $email,
];

$tests[] = [
    'name' => 'Auth::user no expone password_hash',
    'expected' => false,
    'result' => is_array($user)
        && array_key_exists(
            'password_hash',
            $user
        ),
];

/*
 * =========================================================
 * 7. LAST LOGIN
 * =========================================================
 */

$statement = $pdo->prepare("
    SELECT last_login_at
    FROM users
    WHERE id = :user_id
    LIMIT 1
");

$statement->execute([
    'user_id' => $userId,
]);

$lastLoginAt = $statement->fetchColumn();

$tests[] = [
    'name' => 'Login actualiza last_login_at',
    'expected' => true,
    'result' => $lastLoginAt !== false
        && $lastLoginAt !== null
        && $lastLoginAt !== '',
];

/*
 * =========================================================
 * 8. LOGIN EXITOSO REGISTRADO
 * =========================================================
 */

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE user_id = :user_id
      AND ip_address = :ip_address
      AND was_successful = 1
      AND was_blocked = 0
");

$statement->execute([
    'user_id' => $userId,
    'ip_address' => $normalTestIp,
]);

$tests[] = [
    'name' => 'Login exitoso queda registrado',
    'expected' => true,
    'result' => (int) $statement->fetchColumn() >= 1,
];

/*
 * =========================================================
 * 9. LOGOUT
 * =========================================================
 */

$auth->logout();

$tests[] = [
    'name' => 'Logout elimina autenticacion',
    'expected' => false,
    'result' => Auth::check(),
];

$tests[] = [
    'name' => 'Auth::id despues de logout',
    'expected' => null,
    'result' => Auth::id(),
];

/*
 * =========================================================
 * 10. USUARIO INACTIVO
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

$result = $auth->attempt(
    $email,
    $password
);

$tests[] = [
    'name' => 'Usuario inactivo no inicia sesion',
    'expected' => false,
    'result' => $result,
];

/*
 * =========================================================
 * 11. SESIÓN EXISTENTE + USUARIO DESACTIVADO
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

$loginAgain = $auth->attempt(
    $email,
    $password
);

if (!$loginAgain) {
    throw new \RuntimeException(
        'No fue posible preparar la prueba de desactivación posterior al login.'
    );
}

$statement = $pdo->prepare("
    UPDATE users
    SET status = 'inactive'
    WHERE id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

$inactiveUser = Auth::user();

$tests[] = [
    'name' => 'Auth::user rechaza cuenta desactivada',
    'expected' => null,
    'result' => $inactiveUser,
];

$tests[] = [
    'name' => 'Cuenta desactivada destruye sesion',
    'expected' => false,
    'result' => Auth::check(),
];

/*
 * Reactivamos para las pruebas de rate limiting.
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
 * 12. RATE LIMIT IP + IDENTIFICADOR
 * =========================================================
 *
 * Cinco fallos reales deben alcanzar el límite.
 * El sexto intento, incluso con password correcto,
 * debe ser rechazado antes de autenticar.
 * =========================================================
 */

$_SERVER['REMOTE_ADDR'] = $pairRateLimitIp;

for ($i = 0; $i < 5; $i++) {
    $auth->attempt(
        $email,
        'PasswordIncorrecto!'
    );
}

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE user_id = :user_id
      AND ip_address = :ip_address
      AND was_successful = 0
      AND was_blocked = 0
");

$statement->execute([
    'user_id' => $userId,
    'ip_address' => $pairRateLimitIp,
]);

$tests[] = [
    'name' => 'Cinco fallos reales quedan registrados',
    'expected' => 5,
    'result' => (int) $statement->fetchColumn(),
];

$blockedCorrectPassword = $auth->attempt(
    $email,
    $password
);

$tests[] = [
    'name' => 'Rate limit bloquea incluso password correcto',
    'expected' => false,
    'result' => $blockedCorrectPassword,
];

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE ip_address = :ip_address
      AND was_blocked = 1
      AND failure_reason = 'rate_limited'
");

$statement->execute([
    'ip_address' => $pairRateLimitIp,
]);

$tests[] = [
    'name' => 'Intento bloqueado queda registrado',
    'expected' => 1,
    'result' => (int) $statement->fetchColumn(),
];

/*
 * El intento bloqueado no debe convertirse en un sexto
 * fallo de contraseña.
 */
$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE user_id = :user_id
      AND ip_address = :ip_address
      AND was_successful = 0
      AND was_blocked = 0
");

$statement->execute([
    'user_id' => $userId,
    'ip_address' => $pairRateLimitIp,
]);

$tests[] = [
    'name' => 'Bloqueo no incrementa fallos de password',
    'expected' => 5,
    'result' => (int) $statement->fetchColumn(),
];

/*
 * =========================================================
 * 13. RATE LIMIT GLOBAL POR IP
 * =========================================================
 *
 * Veinte correos diferentes desde la misma IP deben
 * bloquear nuevos intentos desde esa IP.
 * =========================================================
 */

$_SERVER['REMOTE_ADDR'] = $globalRateLimitIp;

for ($i = 1; $i <= 20; $i++) {
    $auth->attempt(
        "attack{$i}@novasyscore.local",
        'PasswordIncorrecto!'
    );
}

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE ip_address = :ip_address
      AND was_successful = 0
      AND was_blocked = 0
");

$statement->execute([
    'ip_address' => $globalRateLimitIp,
]);

$tests[] = [
    'name' => 'Veinte fallos globales por IP registrados',
    'expected' => 20,
    'result' => (int) $statement->fetchColumn(),
];

$globalIpBlocked = $auth->attempt(
    'cuenta.nueva@novasyscore.local',
    'PasswordIncorrecto!'
);

$tests[] = [
    'name' => 'IP atacante queda limitada globalmente',
    'expected' => false,
    'result' => $globalIpBlocked,
];

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE ip_address = :ip_address
      AND was_blocked = 1
      AND failure_reason = 'rate_limited'
");

$statement->execute([
    'ip_address' => $globalRateLimitIp,
]);

$tests[] = [
    'name' => 'Bloqueo global por IP queda registrado',
    'expected' => 1,
    'result' => (int) $statement->fetchColumn(),
];

/*
 * =========================================================
 * 14. EL RATE LIMIT NO CREA SESIÓN
 * =========================================================
 */

$tests[] = [
    'name' => 'Intento rate-limited no crea sesion',
    'expected' => false,
    'result' => Auth::check(),
];

/*
 * =========================================================
 * RESTAURAR LABORATORIO
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

cleanAuthenticationAttempts(
    $pdo,
    $testIps
);

/*
 * Restauramos REMOTE_ADDR.
 */
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
echo 'NovaSysCore - Authentication Test' . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;

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
        "[%s] %-48s esperado=%s obtenido=%s",
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

echo str_repeat('=', 78) . PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;
echo PHP_EOL;

ob_end_flush();

exit(
    $failed === 0
        ? 0
        : 1
);