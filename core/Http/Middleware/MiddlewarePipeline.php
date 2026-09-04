<?php

namespace NovaSysCore\Http\Middleware;

use RuntimeException;

class MiddlewarePipeline
{
    public function run(
        array $middlewares,
        callable $destination
    ): void {
        $next = $destination;

        foreach (
            array_reverse($middlewares)
            as $middleware
        ) {
            $next = function () use (
                $middleware,
                $next
            ): void {
                $instance = $this->resolve(
                    $middleware
                );

                $instance->handle(
                    $next
                );
            };
        }

        $next();
    }

    private function resolve(
        object|string $middleware
    ): MiddlewareInterface {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (
            !is_string($middleware)
            || !class_exists($middleware)
        ) {
            throw new RuntimeException(
                'El middleware indicado no existe.'
            );
        }

        $instance = new $middleware();

        if (!$instance instanceof MiddlewareInterface) {
            throw new RuntimeException(
                'El middleware debe implementar MiddlewareInterface.'
            );
        }

        return $instance;
    }
}