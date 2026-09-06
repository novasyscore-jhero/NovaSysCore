<?php

namespace NovaSysCore\Http\Middleware;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): void
    {
        /*
         * Evita que el navegador intente interpretar un recurso
         * con un MIME type diferente al declarado.
         */
        header(
            'X-Content-Type-Options: nosniff'
        );

        /*
         * Evita que NovaSysCore pueda ser incrustado en frames
         * de otros sitios.
         */
        header(
            'X-Frame-Options: DENY'
        );

        /*
         * Limita la información enviada mediante Referer.
         */
        header(
            'Referrer-Policy: strict-origin-when-cross-origin'
        );

        /*
         * Deshabilitamos APIs del navegador que NovaSysCore
         * actualmente no necesita.
         */
        header(
            'Permissions-Policy: '
            . 'camera=(), '
            . 'microphone=(), '
            . 'geolocation=()'
        );

        /*
         * CSP inicial.
         *
         * Actualmente permitimos estilos inline porque las vistas
         * existentes contienen CSS embebido. Cuando migremos CSS
         * a archivos propios podremos endurecer esta política.
         */
        header(
            "Content-Security-Policy: "
            . "default-src 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'none'; "
            . "object-src 'none'; "
            . "script-src 'self'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; "
            . "font-src 'self'; "
            . "connect-src 'self'"
        );

        $next();
    }
}