<?php

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Auth\SessionManager;

$tests = [];

function addTest(
    array &$tests,
    string $name,
    bool $result
): void {
    $tests[] = [
        'name' => $name,
        'result' => $result,
    ];
}

function resetTestSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }

    $_SESSION = [];
}

/*
 * =========================================================
 * 1. CONFIGURACIÓN NATIVA DE PHP
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->start();

addTest(
    $tests,
    'PHP usa solamente cookies para la sesion',
    ini_get('session.use_only_cookies') === '1'
);

addTest(
    $tests,
    'PHP tiene strict mode habilitado',
    ini_get('session.use_strict_mode') === '1'
);

addTest(
    $tests,
    'PHP no permite SID por URL',
    ini_get('session.use_trans_sid') === '0'
);

/*
 * =========================================================
 * 2. SESIÓN AUTENTICADA VÁLIDA
 * =========================================================
 */

$session->login(1001);

addTest(
    $tests,
    'Login crea una sesion autenticada valida',
    $session->check()
);

addTest(
    $tests,
    'La sesion conserva el ID del usuario',
    $session->userId() === 1001
);

/*
 * =========================================================
 * 3. EXPIRACIÓN POR INACTIVIDAD
 * =========================================================
 */

$_SESSION['auth_last_activity_at'] =
    time() - (31 * 60);

addTest(
    $tests,
    'La sesion expira despues de 30 minutos de inactividad',
    $session->check() === false
);

addTest(
    $tests,
    'La expiracion elimina la identidad autenticada',
    $session->userId() === null
);

/*
 * =========================================================
 * 4. EXPIRACIÓN ABSOLUTA
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->login(1002);

$_SESSION['auth_started_at'] =
    time() - (9 * 3600);

$_SESSION['auth_last_activity_at'] =
    time();

addTest(
    $tests,
    'La sesion expira al superar su duracion absoluta',
    $session->check() === false
);

addTest(
    $tests,
    'La expiracion absoluta elimina la identidad',
    $session->userId() === null
);

/*
 * =========================================================
 * 5. SESIÓN ANÓNIMA
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->start();

$_SESSION['csrf_test_value'] = 'alive';

$session->start();

addTest(
    $tests,
    'Una sesion anonima no aplica timeout de autenticacion',
    isset($_SESSION['csrf_test_value'])
        && $_SESSION['csrf_test_value'] === 'alive'
);

/*
 * =========================================================
 * 6. METADATOS DE AUTENTICACIÓN INCOMPLETOS
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->start();

$_SESSION['auth_user_id'] = 1003;

addTest(
    $tests,
    'Una sesion autenticada sin metadatos temporales se invalida',
    $session->check() === false
);

/*
 * =========================================================
 * LIMPIEZA
 * =========================================================
 */

resetTestSession();

/*
 * =========================================================
 * RESULTADOS
 * =========================================================
 */

echo PHP_EOL;

echo 'NovaSysCore - Session Security Test'
    . PHP_EOL;

echo str_repeat('=', 78)
    . PHP_EOL;

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    if ($test['result']) {
        $passed++;
        $status = 'OK';
    } else {
        $failed++;
        $status = 'FAIL';
    }

    echo sprintf(
        "[%s] %s",
        $status,
        $test['name']
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

exit(
    $failed === 0
        ? 0
        : 1
);