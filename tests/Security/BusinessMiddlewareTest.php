<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use NovaSysCore\Http\Middleware\AuthMiddleware;
use NovaSysCore\Http\Middleware\AuthorizationMiddleware;
use NovaSysCore\Http\Middleware\BusinessMiddleware;
use NovaSysCore\Http\Middleware\CompanyContextMiddleware;

echo PHP_EOL;
echo "NovaSysCore - Business Middleware Test" . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;

$correct = 0;
$failed = 0;

function check(
    string $label,
    bool $condition
): void {
    global $correct, $failed;

    if ($condition) {
        echo "[OK] {$label}" . PHP_EOL;
        $correct++;
        return;
    }

    echo "[FAIL] {$label}" . PHP_EOL;
    $failed++;
}

$middlewares =
    BusinessMiddleware::permission(
        'users.view'
    );

check(
    'Devuelve exactamente tres middlewares',
    count($middlewares) === 3
);

check(
    'Primer middleware es AuthMiddleware',
    $middlewares[0] === AuthMiddleware::class
);

check(
    'Segundo middleware es CompanyContextMiddleware',
    $middlewares[1] === CompanyContextMiddleware::class
);

check(
    'Tercer middleware es AuthorizationMiddleware',
    $middlewares[2] instanceof AuthorizationMiddleware
);

echo str_repeat('-', 78) . PHP_EOL;
echo "Correctas: {$correct}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;