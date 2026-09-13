<?php

namespace NovaSysCore\Http\Middleware;

final class BusinessMiddleware
{
    /**
     * Devuelve la cadena estándar de middlewares
     * para una ruta empresarial protegida por permiso.
     */
    public static function permission(
        string $permission
    ): array {
        return [
            AuthMiddleware::class,
            CompanyContextMiddleware::class,
            new AuthorizationMiddleware($permission),
        ];
    }
}