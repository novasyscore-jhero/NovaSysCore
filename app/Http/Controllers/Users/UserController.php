<?php

namespace App\Http\Controllers\Users;

use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Database;
use NovaSysCore\Url;
use App\Services\Users\CompanyUserService;
use App\Exceptions\Users\MembershipExistsException;
use App\Exceptions\Users\MembershipInactiveException;
use App\Exceptions\Users\UserInactiveException;

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

    public function create(): void
    {
        $context = CompanyContextStore::get();

        if ($context === null) {
            http_response_code(403);

            echo 'No existe un contexto empresarial válido.';

            return;
        }

        require dirname(__DIR__, 3)
            . '/Views/users/create.php';
    }

    public function store(): void
    {
        $context = CompanyContextStore::get();

        if ($context === null) {
            http_response_code(403);

            echo 'No existe un contexto empresarial válido.';

            return;
        }

        $name = trim(
            (string) ($_POST['name'] ?? '')
        );

        $lastName = trim(
            (string) ($_POST['last_name'] ?? '')
        );

        $displayName = trim(
            (string) ($_POST['display_name'] ?? '')
        );

        $email = trim(
            (string) ($_POST['email'] ?? '')
        );

        $phone = trim(
            (string) ($_POST['phone'] ?? '')
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );

        /*
         * =====================================================
         * VALIDACIÓN
         * =====================================================
         */

        $errors = [];

        if ($name === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        if (mb_strlen($name) > 100) {
            $errors[] = 'El nombre es demasiado largo.';
        }

        if (mb_strlen($lastName) > 150) {
            $errors[] = 'Los apellidos son demasiado largos.';
        }

        if (mb_strlen($displayName) > 150) {
            $errors[] = 'El nombre para mostrar es demasiado largo.';
        }

        if (
            $email === ''
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $errors[] = 'El correo electrónico no es válido.';
        }

        if (mb_strlen($email) > 180) {
            $errors[] = 'El correo electrónico es demasiado largo.';
        }

        if (mb_strlen($phone) > 50) {
            $errors[] = 'El teléfono es demasiado largo.';
        }

        if (strlen($password) < 8) {
            $errors[] =
                'La contraseña debe tener al menos 8 caracteres.';
        }

        if ($errors !== []) {
            http_response_code(422);

            foreach ($errors as $error) {
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );

                echo '<br>';
            }

            return;
        }

        /*
         * Todavía no guardamos.
         *
         * Este mensaje es temporal mientras verificamos
         * autorización, CSRF y validación.
         */

        /*
         * =====================================================
         * ALTA EMPRESARIAL
         * =====================================================
         */

        $service = new CompanyUserService();

        try {
            $userId = $service->createOrAttach(
                $context->companyId(),
                $name,
                $lastName,
                $displayName,
                $email,
                $phone,
                $password
            );

            header(
                'Location: '
                . Url::to('/users/' . $userId)
            );

            exit;
        } catch (UserInactiveException $exception) {
            http_response_code(409);

            echo 'La identidad existe, pero no está activa.';

            return;
        } catch (MembershipExistsException $exception) {
            http_response_code(409);

            echo 'El usuario ya pertenece a esta empresa.';

            return;
        } catch (MembershipInactiveException $exception) {
            http_response_code(409);

            echo 'El usuario tiene una membresía inactiva en esta empresa.';

            return;
        }
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
                u.status AS user_status,

                uc.id AS membership_id,
                uc.status AS membership_status,
                uc.created_at AS membership_created_at,

                c.id AS company_id,
                c.name AS company_name

            FROM users u

            INNER JOIN user_companies uc
                ON uc.user_id = u.id
                AND uc.company_id = :company_id
                AND uc.status = 'active'

            INNER JOIN companies c
                ON c.id = uc.company_id
                AND c.status = 'active'

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