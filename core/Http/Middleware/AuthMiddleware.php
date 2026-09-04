<?php

namespace NovaSysCore\Http\Middleware;

use NovaSysCore\Auth\Auth;
use NovaSysCore\Url;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        $user = Auth::user();

        if ($user === null) {
            header(
                'Location: ' . Url::to('/login')
            );

            exit;
        }

        $next();
    }
}