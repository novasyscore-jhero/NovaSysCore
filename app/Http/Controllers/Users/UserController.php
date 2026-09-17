<?php

namespace App\Http\Controllers\Users;

use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Database;

class UserController
{
    public function index(): void
    {
        $context = CompanyContextStore::get();

        if ($context === null) {
            http_response_code(403);

            echo 'No existe un contexto empresarial válido.';

            return;
        }

        $pdo = Database::connection();

        $statement = $pdo->prepare("
            SELECT
                u.id,
                u.email,
                u.name,
                u.last_name,
                u.display_name,
                u.status
            FROM users u

            INNER JOIN user_companies uc
                ON uc.user_id = u.id
                AND uc.company_id = :company_id
                AND uc.status = 'active'

            WHERE u.status = 'active'

            ORDER BY
                COALESCE(
                    NULLIF(u.display_name, ''),
                    u.name,
                    u.email
                ),
                u.id
        ");

        $statement->execute([
            'company_id' => $context->companyId(),
        ]);

        $users = $statement->fetchAll();

        require dirname(__DIR__, 3)
            . '/Views/users/index.php';
    }

    public function show(
        string $id
    ): void {
        /*
         * El Router entrega parámetros como string.
         * Para este recurso solamente aceptamos IDs
         * enteros positivos.
         */
        if (
            !ctype_digit($id)
            || (int) $id <= 0
        ) {
            $this->notFound();

            return;
        }

        $context = CompanyContextStore::get();

        if ($context === null) {
            http_response_code(403);

            echo 'No existe un contexto empresarial válido.';

            return;
        }

        $pdo = Database::connection();

        $statement = $pdo->prepare("
        SELECT
            u.id,
            u.email,
            u.name,
            u.last_name,
            u.display_name,
            u.status
        FROM users u

        INNER JOIN user_companies uc
            ON uc.user_id = u.id
            AND uc.company_id = :company_id
            AND uc.status = 'active'

        WHERE u.id = :user_id
          AND u.status = 'active'

        LIMIT 1
    ");

        $statement->execute([
            'company_id' => $context->companyId(),
            'user_id' => (int) $id,
        ]);

        $user = $statement->fetch();

        if (!$user) {
            $this->notFound();

            return;
        }

        /*
         * La vista la agregaremos en el siguiente paso.
         */
        echo htmlspecialchars(
            $user['display_name']
            ?: trim(
                ($user['name'] ?? '')
                . ' '
                . ($user['last_name'] ?? '')
            )
            ?: $user['email'],
            ENT_QUOTES,
            'UTF-8'
        );
    }

    private function notFound(): void
    {
        http_response_code(404);

        echo 'Usuario no encontrado.';
    }

}