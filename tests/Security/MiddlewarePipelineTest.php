<?php

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Http\Middleware\MiddlewareInterface;
use NovaSysCore\Http\Middleware\MiddlewarePipeline;

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

/*
 * Middleware de prueba A.
 */
class TestMiddlewareA implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        $GLOBALS['pipeline_log'][] = 'A:before';

        $next();

        $GLOBALS['pipeline_log'][] = 'A:after';
    }
}

/*
 * Middleware de prueba B.
 */
class TestMiddlewareB implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        $GLOBALS['pipeline_log'][] = 'B:before';

        $next();

        $GLOBALS['pipeline_log'][] = 'B:after';
    }
}

/*
 * Middleware que bloquea la cadena.
 */
class BlockingMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        $GLOBALS['pipeline_log'][] = 'BLOCKED';

        /*
         * Deliberadamente no ejecuta $next().
         */
    }
}

/*
 * ============================================================
 * PRUEBA 1
 * Orden correcto de ejecución.
 * ============================================================
 */

$GLOBALS['pipeline_log'] = [];

$pipeline = new MiddlewarePipeline();

$pipeline->run(
    [
        TestMiddlewareA::class,
        TestMiddlewareB::class,
    ],
    function (): void {
        $GLOBALS['pipeline_log'][] = 'DESTINATION';
    }
);

test(
    $GLOBALS['pipeline_log'] === [
        'A:before',
        'B:before',
        'DESTINATION',
        'B:after',
        'A:after',
    ],
    'Ejecuta los middlewares en el orden correcto.'
);

/*
 * ============================================================
 * PRUEBA 2
 * Un middleware puede detener la petición.
 * ============================================================
 */

$GLOBALS['pipeline_log'] = [];

$pipeline->run(
    [
        TestMiddlewareA::class,
        BlockingMiddleware::class,
        TestMiddlewareB::class,
    ],
    function (): void {
        $GLOBALS['pipeline_log'][] = 'DESTINATION';
    }
);

test(
    $GLOBALS['pipeline_log'] === [
        'A:before',
        'BLOCKED',
        'A:after',
    ],
    'Un middleware puede detener correctamente la cadena.'
);

/*
 * ============================================================
 * PRUEBA 3
 * Soporta instancias ya creadas.
 * ============================================================
 */

$GLOBALS['pipeline_log'] = [];

$pipeline->run(
    [
        new TestMiddlewareA(),
    ],
    function (): void {
        $GLOBALS['pipeline_log'][] = 'DESTINATION';
    }
);

test(
    $GLOBALS['pipeline_log'] === [
        'A:before',
        'DESTINATION',
        'A:after',
    ],
    'Acepta instancias de middleware.'
);

/*
 * ============================================================
 * PRUEBA 4
 * Rechaza clases inexistentes.
 * ============================================================
 */

$exceptionThrown = false;

try {
    $pipeline->run(
        [
            'MiddlewareQueNoExiste',
        ],
        function (): void {
        }
    );
} catch (\RuntimeException) {
    $exceptionThrown = true;
}

test(
    $exceptionThrown,
    'Rechaza middlewares inexistentes.'
);

/*
 * ============================================================
 * PRUEBA 5
 * Rechaza clases que no implementan el contrato.
 * ============================================================
 */

class InvalidMiddleware
{
}

$exceptionThrown = false;

try {
    $pipeline->run(
        [
            InvalidMiddleware::class,
        ],
        function (): void {
        }
    );
} catch (\RuntimeException) {
    $exceptionThrown = true;
}

test(
    $exceptionThrown,
    'Rechaza clases que no implementan MiddlewareInterface.'
);

echo PHP_EOL;
echo "Correctas: {$passed}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);