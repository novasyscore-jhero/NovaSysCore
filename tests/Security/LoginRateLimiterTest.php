<?php

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Database;
use NovaSysCore\Security\LoginRateLimiter;

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

$pdo = Database::connection();

/*
 * ============================================================
 * LIMPIEZA DEL ESCENARIO DE PRUEBA
 * ============================================================
 */

$testIps = [
    '127.0.10.1',
    '127.0.10.2',
];

$placeholders = implode(
    ',',
    array_fill(
        0,
        count($testIps),
        '?'
    )
);

$statement = $pdo->prepare("
    DELETE FROM login_attempts
    WHERE ip_address IN ({$placeholders})
");

$statement->execute(
    $testIps
);

$limiter = new LoginRateLimiter();

/*
 * ============================================================
 * PRUEBA 1
 * Una combinación limpia no debe estar bloqueada.
 * ============================================================
 */

$identifier = 'rate.test@novasyscore.local';
$ip = '127.0.10.1';

test(
    !$limiter->isBlocked(
        $identifier,
        $ip
    ),
    'Una combinacion IP + cuenta limpia no esta bloqueada.'
);

/*
 * ============================================================
 * PRUEBA 2
 * Cuatro fallos todavía deben permitir intento.
 * ============================================================
 */

for ($i = 0; $i < 4; $i++) {
    $limiter->recordFailure(
        $identifier,
        $ip
    );
}

test(
    !$limiter->isBlocked(
        $identifier,
        $ip
    ),
    'Cuatro fallos no superan el limite por cuenta e IP.'
);

/*
 * ============================================================
 * PRUEBA 3
 * El quinto fallo activa el limite de la combinación.
 * ============================================================
 */

$limiter->recordFailure(
    $identifier,
    $ip
);

test(
    $limiter->isBlocked(
        $identifier,
        $ip
    ),
    'Cinco fallos bloquean temporalmente la combinacion IP + cuenta.'
);

/*
 * ============================================================
 * PRUEBA 4
 * Otra cuenta desde otra IP debe seguir libre.
 * ============================================================
 */

test(
    !$limiter->isBlocked(
        'otro.usuario@novasyscore.local',
        '127.0.10.2'
    ),
    'Otra IP y otra cuenta permanecen independientes.'
);

/*
 * ============================================================
 * PRUEBA 5
 * Comprobar límite global por IP.
 * ============================================================
 */

$ipGlobal = '127.0.10.2';

for ($i = 0; $i < 20; $i++) {
    $limiter->recordFailure(
        "usuario{$i}@novasyscore.local",
        $ipGlobal
    );
}

test(
    $limiter->isBlocked(
        'cuenta.nueva@novasyscore.local',
        $ipGlobal
    ),
    'Veinte fallos desde una misma IP bloquean la IP aunque cambie la cuenta.'
);

/*
 * ============================================================
 * PRUEBA 6
 * Registrar bloqueo.
 * ============================================================
 */

$limiter->recordBlocked(
    'cuenta.nueva@novasyscore.local',
    $ipGlobal
);

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE ip_address = :ip_address
      AND was_blocked = 1
      AND failure_reason = 'rate_limited'
");

$statement->execute([
    'ip_address' => $ipGlobal,
]);

test(
    (int) $statement->fetchColumn() >= 1,
    'Registra correctamente los intentos bloqueados.'
);

/*
 * ============================================================
 * PRUEBA 7
 * Registrar éxito.
 * ============================================================
 */

$limiter->recordSuccess(
    'rate.test@novasyscore.local',
    '127.0.10.1',
    1
);

$statement = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE ip_address = :ip_address
      AND was_successful = 1
");

$statement->execute([
    'ip_address' => '127.0.10.1',
]);

test(
    (int) $statement->fetchColumn() >= 1,
    'Registra correctamente los intentos exitosos.'
);

/*
 * ============================================================
 * RESULTADO
 * ============================================================
 */

echo PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;

/*
 * Limpieza final.
 */

$statement = $pdo->prepare("
    DELETE FROM login_attempts
    WHERE ip_address IN ({$placeholders})
");

$statement->execute(
    $testIps
);

exit(
    $failed === 0
        ? 0
        : 1
);
