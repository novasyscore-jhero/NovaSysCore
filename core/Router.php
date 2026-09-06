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
         * Aunque la ruta no exista, los middlewares
         * de respuesta ya fueron ejecutados.
         */
        if (
            !isset(
                $this->routes[$method][$uri]
            )
        ) {
            http_response_code(404);

            echo 'Ruta no encontrada';

            return;
        }

        $route =
            $this->routes[$method][$uri];

        $middlewares = array_merge(
            $this->globalMiddlewares,
            $route['middlewares']
        );

        $pipeline =
            new MiddlewarePipeline();

        $pipeline->run(
            $middlewares,
            function () use ($route): void {
                call_user_func(
                    $route['action']
                );
            }
        );
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