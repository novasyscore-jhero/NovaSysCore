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
    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
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
    ini_get(
        'session.use_only_cookies'
    ) === '1'
);

addTest(
    $tests,
    'PHP tiene strict mode habilitado',
    ini_get(
        'session.use_strict_mode'
    ) === '1'
);

addTest(
    $tests,
    'PHP no permite SID por URL',
    ini_get(
        'session.use_trans_sid'
    ) === '0'
);

/*
 * =========================================================
 * 2. NUEVO LOGIN NO HEREDA CONTEXTO ANTERIOR
 * =========================================================
 */

$session->setCompanyContext(
    9001,
    9002
);

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

addTest(
    $tests,
    'Un nuevo login elimina contexto empresarial previo',
    $session->companyContextId() === null
        && $session->branchContextId()
            === null
);

/*
 * =========================================================
 * 3. CONTEXTO EMPRESARIAL EN SESIÓN
 * =========================================================
 */

$session->setCompanyContext(
    2001,
    3001
);

addTest(
    $tests,
    'La sesion conserva la empresa seleccionada',
    $session->companyContextId() === 2001
);

addTest(
    $tests,
    'La sesion conserva la sucursal seleccionada',
    $session->branchContextId() === 3001
);

/*
 * Cambiar la sucursal debe reemplazar la anterior.
 */
$session->setCompanyContext(
    2001,
    3002
);

addTest(
    $tests,
    'La sesion puede cambiar la sucursal seleccionada',
    $session->companyContextId() === 2001
        && $session->branchContextId()
            === 3002
);

/*
 * Seleccionar solamente empresa elimina la
 * sucursal anterior.
 */
$session->setCompanyContext(
    2002
);

addTest(
    $tests,
    'El contexto puede existir solamente con empresa',
    $session->companyContextId() === 2002
        && $session->branchContextId()
            === null
);

/*
 * Limpiamos manualmente el contexto.
 */
$session->setCompanyContext(
    2001,
    3001
);

$session->clearCompanyContext();

addTest(
    $tests,
    'El contexto empresarial puede limpiarse',
    $session->companyContextId() === null
        && $session->branchContextId()
            === null
);

/*
 * =========================================================
 * 4. VALIDACIÓN DE IDs DEL CONTEXTO
 * =========================================================
 */

$invalidCompanyRejected = false;

try {
    $session->setCompanyContext(0);
} catch (\InvalidArgumentException) {
    $invalidCompanyRejected = true;
}

addTest(
    $tests,
    'Rechaza un ID de empresa invalido',
    $invalidCompanyRejected
);

$invalidBranchRejected = false;

try {
    $session->setCompanyContext(
        2001,
        0
    );
} catch (\InvalidArgumentException) {
    $invalidBranchRejected = true;
}

addTest(
    $tests,
    'Rechaza un ID de sucursal invalido',
    $invalidBranchRejected
);

/*
 * =========================================================
 * 5. EXPIRACIÓN POR INACTIVIDAD
 * =========================================================
 */

$session->setCompanyContext(
    2001,
    3001
);

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

addTest(
    $tests,
    'La expiracion por inactividad elimina el contexto',
    $session->companyContextId() === null
        && $session->branchContextId()
            === null
);

/*
 * =========================================================
 * 6. EXPIRACIÓN ABSOLUTA
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->login(1002);

$session->setCompanyContext(
    2002,
    3002
);

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

addTest(
    $tests,
    'La expiracion absoluta elimina el contexto',
    $session->companyContextId() === null
        && $session->branchContextId()
            === null
);

/*
 * =========================================================
 * 7. LOGOUT
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->login(1003);

$session->setCompanyContext(
    2003,
    3003
);

$session->logout();

addTest(
    $tests,
    'Logout elimina la identidad autenticada',
    $session->check() === false
);

addTest(
    $tests,
    'Logout elimina el contexto empresarial',
    $session->companyContextId() === null
        && $session->branchContextId()
            === null
);

/*
 * =========================================================
 * 8. SESIÓN ANÓNIMA
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->start();

$_SESSION[
    'csrf_test_value'
] = 'alive';

$session->start();

addTest(
    $tests,
    'Una sesion anonima no aplica timeout de autenticacion',
    isset(
        $_SESSION[
            'csrf_test_value'
        ]
    )
        && $_SESSION[
            'csrf_test_value'
        ] === 'alive'
);

/*
 * =========================================================
 * 9. METADATOS DE AUTENTICACIÓN INCOMPLETOS
 * =========================================================
 */

resetTestSession();

$session = new SessionManager();
$session->start();

$_SESSION[
    'auth_user_id'
] = 1004;

$_SESSION[
    'context_company_id'
] = 2004;

$_SESSION[
    'context_branch_id'
] = 3004;

addTest(
    $tests,
    'Una sesion autenticada sin metadatos temporales se invalida',
    $session->check() === false
);

addTest(
    $tests,
    'Una sesion invalida tampoco conserva contexto empresarial',
    $session->companyContextId() === null
        && $session->branchContextId()
            === null
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

echo str_repeat(
    '=',
    78
) . PHP_EOL;

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

echo str_repeat(
    '-',
    78
) . PHP_EOL;

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