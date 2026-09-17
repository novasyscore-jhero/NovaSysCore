<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

ob_start();

use NovaSysCore\Router;

echo PHP_EOL;
echo "NovaSysCore - Router Parameters Test" . PHP_EOL;
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

function dispatchRouter(
    Router $router,
    string $uri,
    string $method = 'GET'
): array {
    http_response_code(200);

    ob_start();

    $router->dispatch(
        $uri,
        $method
    );

    return [
        'status' => http_response_code(),
        'output' => ob_get_clean(),
    ];
}

/*
|--------------------------------------------------------------------------
| 1. Las rutas estáticas actuales siguen funcionando
|--------------------------------------------------------------------------
*/

$router = new Router();

$router->get(
    '/users',
    function (): void {
        echo 'users-index';
    }
);

$result = dispatchRouter(
    $router,
    '/users'
);

check(
    'Ruta estatica sigue funcionando',
    $result['status'] === 200
        && $result['output'] === 'users-index'
);

/*
|--------------------------------------------------------------------------
| 2. Parámetro dinámico simple
|--------------------------------------------------------------------------
*/

$router = new Router();

$receivedId = null;

$router->get(
    '/users/{id}',
    function (string $id) use (&$receivedId): void {
        $receivedId = $id;

        echo 'user-' . $id;
    }
);

$result = dispatchRouter(
    $router,
    '/users/15'
);

check(
    'Ruta dinamica encuentra /users/{id}',
    $result['status'] === 200
);

check(
    'Parametro id llega al action',
    $receivedId === '15'
);

check(
    'Action dinamico se ejecuta correctamente',
    $result['output'] === 'user-15'
);

/*
|--------------------------------------------------------------------------
| 3. Múltiples parámetros
|--------------------------------------------------------------------------
*/

$router = new Router();

$receivedCompany = null;
$receivedUser = null;

$router->get(
    '/companies/{company}/users/{user}',
    function (
        string $company,
        string $user
    ) use (
        &$receivedCompany,
        &$receivedUser
    ): void {
        $receivedCompany = $company;
        $receivedUser = $user;
    }
);

$result = dispatchRouter(
    $router,
    '/companies/7/users/42'
);

check(
    'Primer parametro dinamico es correcto',
    $receivedCompany === '7'
);

check(
    'Segundo parametro dinamico es correcto',
    $receivedUser === '42'
);

/*
|--------------------------------------------------------------------------
| 4. Una ruta estática debe tener prioridad
|--------------------------------------------------------------------------
*/

$router = new Router();

$matched = null;

$router->get(
    '/users/{id}',
    function (string $id) use (&$matched): void {
        $matched = 'dynamic:' . $id;
    }
);

$router->get(
    '/users/create',
    function () use (&$matched): void {
        $matched = 'static';
    }
);

dispatchRouter(
    $router,
    '/users/create'
);

check(
    'Ruta estatica tiene prioridad sobre dinamica',
    $matched === 'static'
);

/*
|--------------------------------------------------------------------------
| 5. Segmentos faltantes no deben coincidir
|--------------------------------------------------------------------------
*/

$router = new Router();

$result = dispatchRouter(
    $router,
    '/companies/7/users'
);

check(
    'Ruta incompleta devuelve 404',
    $result['status'] === 404
);

echo str_repeat('-', 78) . PHP_EOL;
echo "Correctas: {$correct}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;
ob_end_flush();