<?php

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Security\CsrfTokenManager;

$passed = 0;
$failed = 0;

function test(
    bool $condition,
    string $description
): void {
    global $passed, $failed;

    if ($condition) {
        $passed++;

        echo "[OK] {$description}" . PHP_EOL;

        return;
    }

    $failed++;

    echo "[FAIL] {$description}" . PHP_EOL;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

session_id(
    'novasyscore-csrf-'
    . bin2hex(random_bytes(8))
);

$csrf = new CsrfTokenManager();

/*
 * 1. Debe generar un token.
 */

$token = $csrf->token();

test(
    is_string($token)
        && strlen($token) === 64,
    'Genera un token CSRF criptograficamente seguro.'
);

/*
 * 2. El token generado debe ser válido.
 */

test(
    $csrf->validate($token),
    'Acepta el token CSRF correcto.'
);

/*
 * 3. Un token incorrecto debe rechazarse.
 */

test(
    !$csrf->validate(
        str_repeat('a', 64)
    ),
    'Rechaza un token CSRF incorrecto.'
);

/*
 * 4. Token vacío.
 */

test(
    !$csrf->validate(''),
    'Rechaza un token CSRF vacio.'
);

/*
 * 5. Token ausente.
 */

test(
    !$csrf->validate(null),
    'Rechaza un token CSRF ausente.'
);

/*
 * 6. Regeneración.
 */

$newToken = $csrf->regenerate();

test(
    $newToken !== $token,
    'Regenera el token CSRF.'
);

/*
 * 7. Token anterior deja de funcionar.
 */

test(
    !$csrf->validate($token),
    'Invalida el token anterior despues de regenerarlo.'
);

/*
 * 8. Nuevo token funciona.
 */

test(
    $csrf->validate($newToken),
    'Acepta el nuevo token regenerado.'
);

echo PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

exit(
    $failed === 0
        ? 0
        : 1
);