<?php

namespace NovaSysCore;

use NovaSysCore\Http\Middleware\CsrfMiddleware;
use NovaSysCore\Http\Middleware\MiddlewarePipeline;
use NovaSysCore\Http\Middleware\SecurityHeadersMiddleware;

class Router
{
    protected array $routes = [];

    /*
     * Se ejecutan sobre respuestas HTTP incluso cuando
     * la ruta solicitada no existe.
     */
    protected array $responseMiddlewares = [
        SecurityHeadersMiddleware::class,
    ];

    /*
     * Se ejecutan únicamente después de encontrar
     * una ruta válida.
     */
    protected array $globalMiddlewares = [
        CsrfMiddleware::class,
    ];

    public function get(
        string $uri,
        callable $action,
        array $middlewares = []
    ): void {
        $this->routes['GET'][$this->normalizeUri($uri)] = [
            'action' => $action,
            'middlewares' => $middlewares,
        ];
    }

    public function post(
        string $uri,
        callable $action,
        array $middlewares = []
    ): void {
        $this->routes['POST'][$this->normalizeUri($uri)] = [
            'action' => $action,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(
        string $uri,
        string $method
    ): void {
        $method = strtoupper($method);

        $uri = $this->removeBasePath($uri);
        $uri = $this->normalizeUri($uri);

        $responsePipeline =
            new MiddlewarePipeline();

        /*
         * SecurityHeadersMiddleware envuelve toda
         * la resolución de la petición.
         */
        $responsePipeline->run(
            $this->responseMiddlewares,
            function () use (
                $uri,
                $method
            ): void {
                $this->dispatchRoute(
                    $uri,
                    $method
                );
            }
        );
    }

    private function executeRoute(
    array $route,
    array $parameters
    ): void {
        $middlewares = array_merge(
            $this->globalMiddlewares,
            $route['middlewares']
        );

        $pipeline =
            new MiddlewarePipeline();

        $pipeline->run(
            $middlewares,
            function () use (
                $route,
                $parameters
            ): void {
                call_user_func_array(
                    $route['action'],
                    $parameters
                );
            }
        );
    }

    private function matchDynamicRoute(
        string $routeUri,
        string $requestUri
    ): ?array {
        /*
        * Una ruta sin parámetros dinámicos ya fue
        * evaluada mediante coincidencia exacta.
        */
        if (!str_contains($routeUri, '{')) {
            return null;
        }

        $routeSegments =
            explode(
                '/',
                trim($routeUri, '/')
            );

        $requestSegments =
            explode(
                '/',
                trim($requestUri, '/')
            );

        /*
        * Una ruta solamente puede coincidir si tiene
        * exactamente el mismo número de segmentos.
        */
        if (
            count($routeSegments)
            !== count($requestSegments)
        ) {
            return null;
        }

        $parameters = [];

        foreach (
            $routeSegments
            as $index => $routeSegment
        ) {
            $requestSegment =
                $requestSegments[$index];

            /*
            * Detectamos segmentos completos del tipo:
            *
            * {id}
            * {user}
            * {company}
            */
            if (
                preg_match(
                    '/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/',
                    $routeSegment
                ) === 1
            ) {
                if ($requestSegment === '') {
                    return null;
                }

                $parameters[] =
                    rawurldecode(
                        $requestSegment
                    );

                continue;
            }

            /*
            * Los segmentos estáticos deben coincidir
            * exactamente.
            */
            if ($routeSegment !== $requestSegment) {
                return null;
            }
        }

        return $parameters;
    }

    public function middleware(
        string|object $middleware
    ): void {
        $this->globalMiddlewares[]
            = $middleware;
    }

    private function dispatchRoute(
    string $uri,
    string $method
    ): void {
    /*
     * Primero intentamos una coincidencia exacta.
     * Esto garantiza que una ruta estática como
     * /users/create tenga prioridad sobre /users/{id}.
     */
    if (isset($this->routes[$method][$uri])) {
        $this->executeRoute(
            $this->routes[$method][$uri],
            []
        );

        return;
    }

    /*
     * Si no existe una coincidencia exacta,
     * buscamos una ruta dinámica.
     */
    foreach (
        $this->routes[$method] ?? []
        as $routeUri => $route
    ) {
        $parameters =
            $this->matchDynamicRoute(
                $routeUri,
                $uri
            );

        if ($parameters === null) {
            continue;
        }

        $this->executeRoute(
            $route,
            $parameters
        );

        return;
    }

    /*
     * Aunque la ruta no exista, los middlewares
     * de respuesta ya fueron ejecutados.
     */
    http_response_code(404);

    echo 'Ruta no encontrada';
}

    private function normalizeUri(
        string $uri
    ): string {
        $path = parse_url(
            $uri,
            PHP_URL_PATH
        );

        if (
            !is_string($path)
            || $path === ''
        ) {
            return '/';
        }

        $path = '/'
            . trim($path, '/');

        return $path === '/'
            ? '/'
            : rtrim($path, '/');
    }

    private function removeBasePath(
        string $uri
    ): string {
        $basePath =
            Config::get(
                'app.base_path'
            );

        if (
            !is_string($basePath)
            || $basePath === ''
        ) {
            return $uri;
        }

        $path = parse_url(
            $uri,
            PHP_URL_PATH
        );

        if (!is_string($path)) {
            return $uri;
        }

        if (
            $path === $basePath
            || str_starts_with(
                $path,
                $basePath . '/'
            )
        ) {
            $path = substr(
                $path,
                strlen($basePath)
            );

            return $path === ''
                ? '/'
                : $path;
        }

        return $uri;
    }
}