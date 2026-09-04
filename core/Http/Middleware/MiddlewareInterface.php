<?php

namespace NovaSysCore\Http\Middleware;

interface MiddlewareInterface
{
    public function handle(callable $next): void;
}