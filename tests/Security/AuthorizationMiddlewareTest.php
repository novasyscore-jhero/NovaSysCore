<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use NovaSysCore\Context\CompanyContext;
use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Http\Middleware\AuthorizationMiddleware;

echo PHP_EOL;
echo "NovaSysCore - Authorization Middleware Test" . PHP_EOL;
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

function captureStatus(callable $callback): array
{
    http_response_code(200);

    ob_start();

    $executed = false;

    $callback($executed);

    $output = ob_get_clean();

    return [
        'status' => http_response_code(),
        'output' => $output,
        'executed' => $executed,
    ];
}

/*
|--------------------------------------------------------------------------
| 1. Sin contexto
|--------------------------------------------------------------------------
*/

CompanyContextStore::clear();

$result = captureStatus(
    function (&$executed): void {
        $middleware =
            new AuthorizationMiddleware('users.view');

        $middleware->handle(
            function () use (&$executed): void {
                $executed = true;
            }
        );
    }
);

check(
    'Sin contexto devuelve 403',
    $result['status'] === 403
);

check(
    'Sin contexto no ejecuta next',
    $result['executed'] === false
);

/*
|--------------------------------------------------------------------------
| 2. Usuario con permiso
|--------------------------------------------------------------------------
|
| Usaremos el usuario de pruebas de autorización.
| Ajusta IDs si tu fixture actual usa otros valores.
|
*/

$pdo = \NovaSysCore\Database::connection();

$userStatement = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = :email
    LIMIT 1
");

$userStatement->execute([
    'email' => 'authorization.test@novasyscore.local',
]);

$userId = (int) $userStatement->fetchColumn();

$companyStatement = $pdo->prepare("
    SELECT id
    FROM companies
    WHERE slug = :slug
    LIMIT 1
");

$companyStatement->execute([
    'slug' => 'empresa-alpha',
]);

$companyId = (int) $companyStatement->fetchColumn();

$branchStatement = $pdo->prepare("
    SELECT id
    FROM branches
    WHERE slug = :slug
      AND company_id = :company_id
    LIMIT 1
");

$branchStatement->execute([
    'slug' => 'sucursal-centro',
    'company_id' => $companyId,
]);

$branchId = (int) $branchStatement->fetchColumn();

CompanyContextStore::set(
    new CompanyContext(
        $userId,
        $companyId,
        $branchId
    )
);

$result = captureStatus(
    function (&$executed): void {
        $middleware =
            new AuthorizationMiddleware('users.view');

        $middleware->handle(
            function () use (&$executed): void {
                $executed = true;
            }
        );
    }
);

check(
    'Usuario autorizado ejecuta next',
    $result['executed'] === true
);

check(
    'Usuario autorizado conserva HTTP 200',
    $result['status'] === 200
);

/*
|--------------------------------------------------------------------------
| 3. Usuario normal sin permiso
|--------------------------------------------------------------------------
*/

$normalUserStatement = $pdo->prepare("
    SELECT id
    FROM users
    WHERE email = :email
    LIMIT 1
");

$normalUserStatement->execute([
    'email' => 'context.normal@novasyscore.local',
]);

$normalUserId =
    (int) $normalUserStatement->fetchColumn();

CompanyContextStore::set(
    new CompanyContext(
        $normalUserId,
        $companyId,
        null
    )
);

$result = captureStatus(
    function (&$executed): void {
        $middleware =
            new AuthorizationMiddleware('users.view');

        $middleware->handle(
            function () use (&$executed): void {
                $executed = true;
            }
        );
    }
);

check(
    'Usuario sin permiso devuelve 403',
    $result['status'] === 403
);

check(
    'Usuario sin permiso no ejecuta next',
    $result['executed'] === false
);

CompanyContextStore::clear();

echo str_repeat('-', 78) . PHP_EOL;
echo "Correctas: {$correct}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;