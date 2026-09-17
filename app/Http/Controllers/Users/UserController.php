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

        /*
         * =====================================================
         * BÚSQUEDA
         * =====================================================
         */

        $search = trim(
            (string) ($_GET['q'] ?? '')
        );

        if (mb_strlen($search) > 100) {
            $search = mb_substr(
                $search,
                0,
                100
            );
        }

        $pdo = Database::connection();

        /*
         * Construimos el filtro solamente cuando existe
         * un término de búsqueda.
         */
        $searchSql = '';

        if ($search !== '') {
            $searchSql = "
            AND (
                u.name LIKE :search_name
                OR u.last_name LIKE :search_last_name
                OR u.display_name LIKE :search_display_name
                OR u.email LIKE :search_email
            )
        ";
        }

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

        {$searchSql}
    ");

        $countStatement->bindValue(
            ':company_id',
            $context->companyId(),
            \PDO::PARAM_INT
        );

        if ($search !== '') {
            $searchPattern =
                '%' . $search . '%';

            $countStatement->bindValue(
                ':search_name',
                $searchPattern,
                \PDO::PARAM_STR
            );

            $countStatement->bindValue(
                ':search_last_name',
                $searchPattern,
                \PDO::PARAM_STR
            );

            $countStatement->bindValue(
                ':search_display_name',
                $searchPattern,
                \PDO::PARAM_STR
            );

            $countStatement->bindValue(
                ':search_email',
                $searchPattern,
                \PDO::PARAM_STR
            );
        }

        $countStatement->execute();

        $totalUsers =
            (int) $countStatement->fetchColumn();

        $totalPages = max(
            1,
            (int) ceil(
                $totalUsers / $perPage
            )
        );

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

        {$searchSql}

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

        if ($search !== '') {
            $statement->bindValue(
                ':search_name',
                $searchPattern,
                \PDO::PARAM_STR
            );

            $statement->bindValue(
                ':search_last_name',
                $searchPattern,
                \PDO::PARAM_STR
            );

            $statement->bindValue(
                ':search_display_name',
                $searchPattern,
                \PDO::PARAM_STR
            );

            $statement->bindValue(
                ':search_email',
                $searchPattern,
                \PDO::PARAM_STR
            );
        }

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