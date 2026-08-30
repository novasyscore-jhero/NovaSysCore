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
 *
 * Debe aceptar espacios y mayúsculas.
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
        && array_key_exists('password_hash', $user),
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
 * 8. LOGOUT
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
 * 9. USUARIO INACTIVO NO PUEDE INICIAR SESIÓN
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
 * 10. SESIÓN EXISTENTE + USUARIO DESACTIVADO
 *
 * Primero reactivamos y autenticamos.
 * Después desactivamos la cuenta directamente en BD.
 * Auth::user() debe detectar el cambio y cerrar la sesión.
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
        var_export($test['expected'], true),
        var_export($test['result'], true)
    );

    echo PHP_EOL;
}

echo str_repeat('-', 78) . PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;
echo PHP_EOL;

/*
 * Enviamos todo el buffer únicamente cuando ya terminamos
 * de manipular sesiones.
 */
ob_end_flush();

exit($failed === 0 ? 0 : 1);