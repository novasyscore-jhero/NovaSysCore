<?php

namespace NovaSysCore;

class Router
{
    protected array $routes = [];

    public function get(string $uri, callable $action): void
    {
        $this->routes['GET'][$this->normalizeUri($uri)] = $action;
    }

    public function post(string $uri, callable $action): void
    {
        $this->routes['POST'][$this->normalizeUri($uri)] = $action;
    }

    public function dispatch(string $uri, string $method): void
    {
        $method = strtoupper($method);

        $uri = $this->removeBasePath(
            $uri
        );

        $uri = $this->normalizeUri(
            $uri
        );

        if (isset($this->routes[$method][$uri])) {

            call_user_func(
                $this->routes[$method][$uri]
            );

            return;
        }

        http_response_code(404);

        echo 'Ruta no encontrada';
    }

    private function normalizeUri(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function removeBasePath(string $uri): string
    {
        $basePath = Config::get(
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