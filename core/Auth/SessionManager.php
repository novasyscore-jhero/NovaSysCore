<?php

namespace NovaSysCore\Auth;

class SessionManager
{
    private const AUTH_USER_KEY = 'auth_user_id';

    private bool $started = false;

    public function start(): void
    {
        if ($this->isStarted()) {
            $this->started = true;
            return;
        }

        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                "No se puede iniciar la sesión porque los encabezados ya fueron enviados en {$file}:{$line}."
            );
        }

        $secure = $this->isHttps();

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new \RuntimeException(
                'No fue posible iniciar la sesión.'
            );
        }

        $this->started = true;
    }

    public function login(int $userId): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException(
                'El ID del usuario autenticado no es válido.'
            );
        }

        $this->start();

        /*
         * Defensa contra session fixation.
         * El ID anterior deja de ser válido después del login.
         */
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException(
                'No fue posible regenerar la sesión.'
            );
        }

        $_SESSION[self::AUTH_USER_KEY] = $userId;
    }

    public function logout(): void
    {
        $this->start();

        unset($_SESSION[self::AUTH_USER_KEY]);

        $_SESSION = [];

        /*
         * Eliminamos también la cookie de sesión del navegador.
         */
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $parameters['path'],
                    'domain' => $parameters['domain'],
                    'secure' => $parameters['secure'],
                    'httponly' => $parameters['httponly'],
                    'samesite' => $parameters['samesite'] ?? 'Lax',
                ]
            );
        }

        if (!session_destroy()) {
            throw new \RuntimeException(
                'No fue posible destruir la sesión.'
            );
        }

        $this->started = false;
    }

    public function check(): bool
    {
        $this->start();

        return isset($_SESSION[self::AUTH_USER_KEY])
            && is_int($_SESSION[self::AUTH_USER_KEY])
            && $_SESSION[self::AUTH_USER_KEY] > 0;
    }

    public function userId(): ?int
    {
        if (!$this->check()) {
            return null;
        }

        return $_SESSION[self::AUTH_USER_KEY];
    }

    private function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    private function isHttps(): bool
    {
        if (
            isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off'
        ) {
            return true;
        }

        return isset($_SERVER['SERVER_PORT'])
            && (int) $_SERVER['SERVER_PORT'] === 443;
    }
}