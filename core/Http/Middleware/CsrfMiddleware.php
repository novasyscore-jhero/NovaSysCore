<?php

namespace NovaSysCore\Http\Middleware;

use NovaSysCore\Security\CsrfTokenManager;

class CsrfMiddleware
{
    private CsrfTokenManager $csrf;

    public function __construct(
        ?CsrfTokenManager $csrf = null
    ) {
        $this->csrf = $csrf
            ?? new CsrfTokenManager();
    }

    public function handle(callable $next): void
    {
        $method = strtoupper(
            $_SERVER['REQUEST_METHOD']
                ?? 'GET'
        );

        if (
            in_array(
                $method,
                [
                    'POST',
                    'PUT',
                    'PATCH',
                    'DELETE',
                ],
                true
            )
        ) {
            $token = $_POST['_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? null;

            if (
                !$this->csrf->validate(
                    is_string($token)
                        ? $token
                        : null
                )
            ) {
                http_response_code(403);

                echo 'La sesión de seguridad ha expirado o la solicitud no es válida.';

                return;
            }
        }

        $next();
    }
}