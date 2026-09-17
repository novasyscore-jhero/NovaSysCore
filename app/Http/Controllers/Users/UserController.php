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

        /*
         * =====================================================
         * PAGINACIÓN
         * =====================================================
         */

        $perPage = 20;

        $page = filter_input(
            INPUT_GET,
            'page',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($page === false || $page === null) {
            $page = 1;
        }

        $pdo = Database::connection();

        /*
         * =====================================================
         * TOTAL DE USUARIOS
         * =====================================================
         */

        $countStatement = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u

        INNER JOIN user_companies uc
            ON uc.user_id = u.id
            AND uc.company_id = :company_id
            AND uc.status = 'active'

        WHERE u.status = 'active'
    ");

        $countStatement->execute([
            'company_id' => $context->companyId(),
        ]);

        $totalUsers =
            (int) $countStatement->fetchColumn();

        $totalPages = max(
            1,
            (int) ceil(
                $totalUsers / $perPage
            )
        );

        /*
         * Si solicitan una página superior a la última,
         * mostramos la última página disponible.
         */
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset =
            ($page - 1) * $perPage;

        /*
         * =====================================================
         * USUARIOS DE LA PÁGINA ACTUAL
         * =====================================================
         */

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

        LIMIT :limit
        OFFSET :offset
    ");

        $statement->bindValue(
            ':company_id',
            $context->companyId(),
            \PDO::PARAM_INT
        );

        $statement->bindValue(
            ':limit',
            $perPage,
            \PDO::PARAM_INT
        );

        $statement->bindValue(
            ':offset',
            $offset,
            \PDO::PARAM_INT
        );

        $statement->execute();

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
        require dirname(__DIR__, 3)
            . '/Views/users/show.php';
    }

    private function notFound(): void
    {
        http_response_code(404);

        echo 'Usuario no encontrado.';
    }

}