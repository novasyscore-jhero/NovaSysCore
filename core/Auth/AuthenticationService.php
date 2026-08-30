<?php

namespace NovaSysCore\Auth;

use NovaSysCore\Database;

class AuthenticationService
{
    private \PDO $pdo;
    private SessionManager $session;

    public function __construct(?SessionManager $session = null)
    {
        $this->pdo = Database::connection();
        $this->session = $session ?? new SessionManager();
    }

    public function attempt(string $email, string $password): bool
    {
        $email = $this->normalizeEmail($email);

        if ($email === '' || $password === '') {
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

        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        /*
         * No revelamos si el correo existe o no.
         */
        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        /*
         * Si PHP cambia en el futuro la configuración recomendada
         * del algoritmo de hashing, renovamos el hash automáticamente.
         */
        if (password_needs_rehash(
            $user['password_hash'],
            PASSWORD_DEFAULT
        )) {
            $this->rehashPassword(
                (int) $user['id'],
                $password
            );
        }

        $this->session->login(
            (int) $user['id']
        );

        $this->updateLastLogin(
            (int) $user['id']
        );

        return true;
    }

    public function logout(): void
    {
        $this->session->logout();
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(
            trim($email)
        );
    }

    private function updateLastLogin(int $userId): void
    {
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