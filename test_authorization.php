<?php

require __DIR__ . '/bootstrap/app.php';

use NovaSysCore\Security\AuthorizationService;

$authorization = new AuthorizationService();

    $tests = [
    [
        'name' => 'Alpha + Centro + users.view',
        'expected' => true,
        'result' => $authorization->can(
            1,
            1,
            'users.view',
            1
        ),
    ],

    [
        'name' => 'Alpha + Norte + users.view',
        'expected' => true,
        'result' => $authorization->can(
            1,
            1,
            'users.view',
            2
        ),
    ],

    [
        'name' => 'Beta + users.view',
        'expected' => false,
        'result' => $authorization->can(
            1,
            2,
            'users.view'
        ),
    ],

    [
        'name' => 'Alpha + users.update',
        'expected' => false,
        'result' => $authorization->can(
            1,
            1,
            'users.update'
        ),
    ],

    [
        'name' => 'Empresa inexistente',
        'expected' => false,
        'result' => $authorization->can(
            1,
            999999,
            'users.view'
        ),
    ],

    [
        'name' => 'Sucursal Beta usando Empresa Alpha',
        'expected' => false,
        'result' => $authorization->can(
            1,
            1,
            'users.view',
            3
        ),
    ],

    [
        'name' => 'System Admin + users.view',
        'expected' => true,
        'result' => $authorization->canSystem(
            1,
            'users.view'
        ),
    ],

    [
        'name' => 'Super Admin + permiso existente',
        'expected' => true,
        'result' => $authorization->canSystem(
            1,
            'users.view'
        ),
    ],

    [
        'name' => 'Super Admin + permiso futuro/inexistente',
        'expected' => true,
        'result' => $authorization->canSystem(
            1,
            'future.module.permission'
        ),
    ],

    [
        'name' => 'Super Admin bypass Empresa Beta',
        'expected' => true,
        'result' => $authorization->can(
            1,
            2,
            'users.view'
        ),
    ],

    [
        'name' => 'Super Admin bypass sin membresia',
        'expected' => true,
        'result' => $authorization->can(
            1,
            2,
            'future.module.permission'
        ),
    ],

    [
        'name' => 'System Admin + roles.assign',
        'expected' => true,
        'result' => $authorization->canSystem(
            1,
            'roles.assign'
        ),
    ],

    [
        'name' => 'Super Admin inactivo',
        'expected' => false,
        'result' => $authorization->canSystem(
            1,
            'users.view'
        ),
    ],

    [
        'name' => 'Super Admin inactivo sin bypass empresa',
        'expected' => false,
        'result' => $authorization->can(
            1,
            2,
            'users.view'
        ),
    ],

    [
        'name' => 'System Admin + permiso inexistente',
        'expected' => false,
        'result' => $authorization->canSystem(
            1,
            'core.permission.does-not-exist'
        ),
    ],

    [
        'name' => 'System Admin NO bypass Empresa Beta',
        'expected' => false,
        'result' => $authorization->can(
            1,
            2,
            'users.view'
        ),
    ],
];

echo PHP_EOL;
echo "NovaSysCore - AuthorizationService Test" . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $success = $test['result'] === $test['expected'];

    if ($success) {
        $passed++;
    } else {
        $failed++;
    }

    echo sprintf(
        "[%s] %-40s esperado=%s obtenido=%s",
        $success ? 'OK' : 'FAIL',
        $test['name'],
        $test['expected'] ? 'ALLOW' : 'DENY',
        $test['result'] ? 'ALLOW' : 'DENY'
    );

    echo PHP_EOL;
}

echo str_repeat('-', 60) . PHP_EOL;

echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;

echo PHP_EOL;

exit($failed === 0 ? 0 : 1);