<?php

namespace NovaSysCore;

use Throwable;
use NovaSysCore\Exceptions\MigrationException;

class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool
    {
        throw new \ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }

    public static function handleException(Throwable $exception): void
    {
        if ($exception instanceof MigrationException) {
            self::renderMigrationException($exception);
            return;
        }

        self::render($exception);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null) {
            self::render(
                new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                )
            );
        }
    }

    protected static function renderMigrationException(
        MigrationException $exception
    ): void
    {
        http_response_code(500);
    
        $debug = Config::get('app.debug');
    
        if ($debug) {
    
            echo "<h1>NovaSysCore - Error de migración</h1>";
            echo "<h2>"
                . htmlspecialchars($exception->getMessage())
                . "</h2>";
    
            echo "<p><strong>Archivo:</strong> "
                . htmlspecialchars($exception->getFile())
                . "</p>";
    
            echo "<p><strong>Línea:</strong> "
                . $exception->getLine()
                . "</p>";
    
            echo "<pre>"
                . htmlspecialchars($exception->getTraceAsString())
                . "</pre>";
    
            return;
        }
    
        echo "<h1>Error durante una migración</h1>";
        echo "<p>Se produjo un problema al actualizar la estructura "
            . "de la base de datos.</p>";
        echo "<p>Por favor contacte al administrador del sistema.</p>";
    }

    protected static function render(Throwable $exception): void
    {
        http_response_code(500);

        $debug = Config::get('app.debug');

        if ($debug) {

            echo "<h1>NovaSysCore - Error del sistema</h1>";
            echo "<h2>" . htmlspecialchars($exception->getMessage()) . "</h2>";
            echo "<p><strong>Archivo:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
            echo "<p><strong>Línea:</strong> " . $exception->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";

            return;
        }

        echo "<h1>Ha ocurrido un error</h1>";
        echo "<p>Por favor contacte al administrador del sistema.</p>";
    }
}