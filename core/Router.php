<?php

namespace NovaSysCore;

class Router
{
    protected array $routes = [];


    public function get(string $uri, callable $action): void
    {
        $this->routes['GET'][$uri] = $action;
    }


    public function post(string $uri, callable $action): void
    {
        $this->routes['POST'][$uri] = $action;
    }


    public function dispatch(string $uri, string $method): void
    {
        if (isset($this->routes[$method][$uri])) {

            call_user_func(
                $this->routes[$method][$uri]
            );

            return;
        }


        http_response_code(404);

        echo "Ruta no encontrada";
    }
}