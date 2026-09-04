<?php

namespace NovaSysCore\Security;

use NovaSysCore\Auth\SessionManager;

class CsrfTokenManager
{
    private const SESSION_KEY = 'csrf_token';

    private SessionManager $session;

    public function __construct(
        ?SessionManager $session = null
    ) {
        $this->session = $session
            ?? new SessionManager();
    }

    public function token(): string
    {
        $this->session->start();

        $token = $_SESSION[self::SESSION_KEY]
            ?? null;

        if (
            !is_string($token)
            || strlen($token) < 32
        ) {
            $token = bin2hex(
                random_bytes(32)
            );

            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public function regenerate(): string
    {
        $this->session->start();

        $token = bin2hex(
            random_bytes(32)
        );

        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    public function validate(?string $token): bool
    {
        $this->session->start();

        if (
            !is_string($token)
            || $token === ''
        ) {
            return false;
        }

        $sessionToken = $_SESSION[self::SESSION_KEY]
            ?? null;

        if (
            !is_string($sessionToken)
            || $sessionToken === ''
        ) {
            return false;
        }

        return hash_equals(
            $sessionToken,
            $token
        );
    }
}