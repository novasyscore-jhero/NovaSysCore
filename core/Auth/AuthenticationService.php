<?php

namespace NovaSysCore\Auth;

use NovaSysCore\Database;
use NovaSysCore\Security\LoginRateLimiter;
use PDO;

class AuthenticationService
{
    private PDO $pdo;
    private SessionManager $session;
    private LoginRateLimiter $rateLimiter;

    public function __construct(
        ?SessionManager $session = null,
        ?LoginRateLimiter $rateLimiter = null
    ) {
        $this->pdo = Database::connection();

        $this->session = $session
            ?? new SessionManager();

        $this->rateLimiter = $rateLimiter
            ?? new LoginRateLimiter();
    }

    public function attempt(
        string $email,
        string $password
    ): bool {
        $email = $this->normalizeEmail($email);
        $ipAddress = $this->getClientIpAddress();

        /*
         * No procesamos credenciales vacías.
         *
         * Tampoco las registramos como intento de autenticación,
         * porque el formulario todavía no contiene credenciales
         * utilizables.
         */
        if ($email === '' || $password === '') {
            return false;
        }

        /*
         * =====================================================
         * RATE LIMIT
         * =====================================================
         *
         * Se comprueba antes de consultar/verificar credenciales
         * costosas.
         */
        if (
            $this->rateLimiter->isBlocked(
                $email,
                $ipAddress
            )
        ) {
            $this->rateLimiter->recordBlocked(
                $email,
                $ipAddress
            );

            return false;
        }

        $statement = $this->pdo->prepare("
            SELECT
                id,
                email,
                password_hash,
                status
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $statement->execute([
            'email' => $email,
        ]);

        $user = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        /*
         * =====================================================
         * USUARIO INEXISTENTE
         * =====================================================
         *
         * Hacia el exterior seguimos devolviendo únicamente
         * false. No revelamos si el correo existe.
         */
        if (!$user) {
            $this->rateLimiter->recordFailure(
                $email,
                $ipAddress,
                null,
                'invalid_credentials'
            );

            return false;
        }

        $userId = (int) $user['id'];

        /*
         * =====================================================
         * USUARIO INACTIVO
         * =====================================================
         *
         * Internamente podemos distinguir el motivo para
         * seguridad/auditoría, pero el cliente no lo conoce.
         */
        if ($user['status'] !== 'active') {
            $this->rateLimiter->recordFailure(
                $email,
                $ipAddress,
                $userId,
                'inactive_user'
            );

            return false;
        }

        /*
         * =====================================================
         * CONTRASEÑA INCORRECTA
         * =====================================================
         */
        if (
            !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            $this->rateLimiter->recordFailure(
                $email,
                $ipAddress,
                $userId,
                'invalid_credentials'
            );

            return false;
        }

        /*
         * =====================================================
         * REHASH AUTOMÁTICO
         * =====================================================
         *
         * Si PHP cambia la configuración recomendada del
         * algoritmo, renovamos el hash automáticamente.
         */
        if (
            password_needs_rehash(
                $user['password_hash'],
                PASSWORD_DEFAULT
            )
        ) {
            $this->rehashPassword(
                $userId,
                $password
            );
        }

        /*
         * =====================================================
         * LOGIN EXITOSO
         * =====================================================
         */
        $this->session->login(
            $userId
        );

        $this->updateLastLogin(
            $userId
        );

        $this->rateLimiter->recordSuccess(
            $email,
            $ipAddress,
            $userId
        );

        return true;
    }

    public function logout(): void
    {
        $this->session->logout();
    }

    private function normalizeEmail(
        string $email
    ): string {
        return strtolower(
            trim($email)
        );
    }

    private function getClientIpAddress(): string
    {
        $ipAddress = $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';

        if (
            !is_string($ipAddress)
            || $ipAddress === ''
        ) {
            return '0.0.0.0';
        }

        /*
         * No confiamos todavía en X-Forwarded-For.
         * Eso se añadirá cuando implementemos trusted proxies.
         */
        return substr(
            $ipAddress,
            0,
            45
        );
    }

    private function updateLastLogin(
        int $userId
    ): void {
        $statement = $this->pdo->prepare("
            UPDATE users
            SET
                last_login_at = NOW(),
                updated_at = NOW()
            WHERE id = :user_id
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);
    }

    private function rehashPassword(
        int $userId,
        string $password
    ): void {
        $newHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($newHash === false) {
            throw new \RuntimeException(
                'No fue posible actualizar el hash de la contraseña.'
            );
        }

        $statement = $this->pdo->prepare("
            UPDATE users
            SET
                password_hash = :password_hash,
                updated_at = NOW()
            WHERE id = :user_id
        ");

        $statement->execute([
            'password_hash' => $newHash,
            'user_id' => $userId,
        ]);
    }
}