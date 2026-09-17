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
}