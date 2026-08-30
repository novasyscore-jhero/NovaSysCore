<?php

namespace NovaSysCore\Auth;

use NovaSysCore\Database;

class Auth
{
    private static ?SessionManager $session = null;

    public static function check(): bool
    {
        return self::session()->check();
    }

    public static function id(): ?int
    {
        return self::session()->userId();
    }

    public static function user(): ?array
    {
        $userId = self::id();

        if ($userId === null) {
            return null;
        }

        $pdo = Database::connection();

        $statement = $pdo->prepare("
            SELECT
                id,
                name,
                last_name,
                display_name,
                email,
                phone,
                avatar,
                status,
                email_verified_at,
                last_login_at,
                created_at,
                updated_at
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);

        $user = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            self::logout();
            return null;
        }

        /*
         * Si el usuario fue desactivado después de iniciar sesión,
         * la sesión deja de considerarse válida.
         */
        if ($user['status'] !== 'active') {
            self::logout();
            return null;
        }

        return $user;
    }

    public static function logout(): void
    {
        self::session()->logout();
    }

    private static function session(): SessionManager
    {
        if (self::$session === null) {
            self::$session = new SessionManager();
        }

        return self::$session;
    }
}