<?php

namespace NovaSysCore\Auth;

use NovaSysCore\Config;

class SessionManager
{
    private const AUTH_USER_KEY = 'auth_user_id';

    private const AUTH_STARTED_AT_KEY =
        'auth_started_at';

    private const AUTH_LAST_ACTIVITY_AT_KEY =
        'auth_last_activity_at';

    private bool $started = false;

    public function start(): void
    {
        if ($this->isStarted()) {
            $this->started = true;

            $this->validateAuthenticationLifetime();

            return;
        }

        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                "No se puede iniciar la sesión porque los encabezados ya fueron enviados en {$file}:{$line}."
            );
        }

        /*
         * Endurecimiento nativo de PHP.
         *
         * - Solo cookies para transportar el ID.
         * - Rechaza IDs de sesión no creados por PHP.
         * - Impide SID en la URL.
         */
        ini_set(
            'session.use_only_cookies',
            '1'
        );

        ini_set(
            'session.use_strict_mode',
            '1'
        );

        ini_set(
            'session.use_trans_sid',
            '0'
        );

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

        $this->validateAuthenticationLifetime();
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
         */
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException(
                'No fue posible regenerar la sesión.'
            );
        }

        $now = time();

        $_SESSION[self::AUTH_USER_KEY] =
            $userId;

        $_SESSION[self::AUTH_STARTED_AT_KEY] =
            $now;

        $_SESSION[
            self::AUTH_LAST_ACTIVITY_AT_KEY
        ] = $now;
    }

    public function logout(): void
    {
        $this->start();

        $_SESSION = [];

        /*
         * Eliminamos también la cookie de sesión
         * del navegador.
         */
        if (ini_get('session.use_cookies')) {
            $parameters =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' =>
                        time() - 42000,
                    'path' =>
                        $parameters['path'],
                    'domain' =>
                        $parameters['domain'],
                    'secure' =>
                        $parameters['secure'],
                    'httponly' =>
                        $parameters['httponly'],
                    'samesite' =>
                        $parameters['samesite']
                        ?? 'Lax',
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

        return isset(
            $_SESSION[self::AUTH_USER_KEY]
        )
            && is_int(
                $_SESSION[self::AUTH_USER_KEY]
            )
            && $_SESSION[self::AUTH_USER_KEY]
                > 0;
    }

    public function userId(): ?int
    {
        if (!$this->check()) {
            return null;
        }

        return $_SESSION[
            self::AUTH_USER_KEY
        ];
    }

    private function validateAuthenticationLifetime(): void
    {
        /*
         * Una sesión anónima, por ejemplo la usada
         * para CSRF antes del login, no tiene timeout
         * de autenticación.
         */
        if (
            !isset(
                $_SESSION[self::AUTH_USER_KEY]
            )
        ) {
            return;
        }

        $startedAt =
            $_SESSION[
                self::AUTH_STARTED_AT_KEY
            ]
            ?? null;

        $lastActivityAt =
            $_SESSION[
                self::AUTH_LAST_ACTIVITY_AT_KEY
            ]
            ?? null;

        if (
            !is_int($startedAt)
            || !is_int($lastActivityAt)
        ) {
            $this->expireAuthentication();

            return;
        }

        $sessionConfig =
            Config::get('security.session');

        if (!is_array($sessionConfig)) {
            $sessionConfig = [];
        }

        $idleTimeoutMinutes =
            (int) (
                $sessionConfig[
                    'idle_timeout_minutes'
                ]
                ?? 30
            );

        $absoluteTimeoutHours =
            (int) (
                $sessionConfig[
                    'absolute_timeout_hours'
                ]
                ?? 8
            );

        $now = time();

        $idleExpired =
            $idleTimeoutMinutes > 0
            && (
                $now - $lastActivityAt
            ) >= (
                $idleTimeoutMinutes * 60
            );

        $absoluteExpired =
            $absoluteTimeoutHours > 0
            && (
                $now - $startedAt
            ) >= (
                $absoluteTimeoutHours
                * 3600
            );

        if (
            $idleExpired
            || $absoluteExpired
        ) {
            $this->expireAuthentication();

            return;
        }

        /*
         * La petición actual cuenta como actividad
         * del usuario autenticado.
         */
        $_SESSION[
            self::AUTH_LAST_ACTIVITY_AT_KEY
        ] = $now;
    }

    private function expireAuthentication(): void
    {
        /*
         * Limpiamos toda la sesión para impedir
         * reutilizar datos asociados a la identidad
         * anterior, incluido el token CSRF.
         */
        $_SESSION = [];

        /*
         * Mantenemos una sesión anónima nueva.
         * Esto permite que la misma petición pueda
         * generar un nuevo token CSRF si lo necesita.
         */
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException(
                'No fue posible renovar la sesión expirada.'
            );
        }
    }

    private function isStarted(): bool
    {
        return session_status()
            === PHP_SESSION_ACTIVE;
    }

    private function isHttps(): bool
    {
        if (
            isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower(
                (string) $_SERVER['HTTPS']
            ) !== 'off'
        ) {
            return true;
        }

        return isset(
            $_SERVER['SERVER_PORT']
        )
            && (int) $_SERVER[
                'SERVER_PORT'
            ] === 443;
    }
}