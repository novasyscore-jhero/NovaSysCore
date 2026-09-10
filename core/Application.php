<?php

namespace NovaSysCore;

use App\Http\Controllers\Auth\LoginController;
use NovaSysCore\Auth\Auth;
use NovaSysCore\Url;
use NovaSysCore\Security\CsrfTokenManager;
use NovaSysCore\Http\Middleware\AuthMiddleware;
use App\Http\Controllers\Context\CompanyContextController;
use NovaSysCore\Http\Middleware\CompanyContextMiddleware;
use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Database;


class Application
{
    protected Container $container;

    public function __construct()
    {
        $this->container = new Container();

        $this->container->bind(
            'router',
            function () {
                return new Router();
            }
        );

        $this->container->bind(
            'audit',
            function () {
                return new AuditLogger();
            }
        );
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function start(): string
    {
        /** @var Router $router */
        $router = $this->container->make('router');

        /*
        * =====================================================
        * CONTEXTO EMPRESARIAL
        * =====================================================
        */

        $router->get(
            '/context',
            function (): void {

                $controller =
                    new CompanyContextController();

                $controller->index();
            },
            [
                AuthMiddleware::class,
            ]
        );

        $router->post(
            '/context/select',
            function (): void {

                $controller =
                    new CompanyContextController();

                $controller->select();
            },
            [
                AuthMiddleware::class,
            ]
        );

        /*
         * =====================================================
         * RUTA INICIAL
         * =====================================================
         */

        $router->get('/', function (): void {

            if (Auth::check()) {
                header(
                    'Location: ' . Url::to('/context')
                );
                exit;
            }

            header(
                'Location: ' . Url::to('/login')
            );
            exit;
        });

        /*
         * =====================================================
         * AUTENTICACIÓN
         * =====================================================
         */

        $router->get('/login', function (): void {

            $controller = new LoginController();

            $controller->show();
        });

        $router->post('/login', function (): void {

            $controller = new LoginController();

            $controller->login();
        });

        $router->post('/logout', function (): void {

            $controller = new LoginController();

            $controller->logout();
        });

        /*
         * =====================================================
         * DASHBOARD TEMPORAL PROTEGIDO
         * =====================================================
         *
         * Más adelante esta protección pasará a Middleware.
         * Por ahora nos permitirá comprobar el flujo HTTP
         * completo antes de construir esa capa.
         */

        $router->get(
            '/dashboard',
            function (): void {

            $context =
                CompanyContextStore::get();

            if ($context === null) {
                return;
            }

            $pdo = Database::connection();

            $statement = $pdo->prepare("
                SELECT
                    c.name AS company_name,
                    b.name AS branch_name
                FROM companies c

                LEFT JOIN branches b
                    ON b.id = :branch_id
                AND b.company_id = c.id

                WHERE c.id = :company_id
                LIMIT 1
            ");

            $statement->execute([
                'company_id' => $context->companyId(),
                'branch_id' => $context->branchId(),
            ]);

            $businessContext =
                $statement->fetch();

            $companyName =
                $businessContext['company_name']
                ?? 'Empresa no disponible';

            $branchName =
                $businessContext['branch_name']
                ?? 'Sin sucursal seleccionada';

                $user = Auth::user();

                if ($user === null) {
                    return;
                }

                $displayName =
                    $user['display_name']
                    ?: trim(
                        ($user['name'] ?? '')
                        . ' '
                        . ($user['last_name'] ?? '')
                    );

                $csrf = new CsrfTokenManager();

                $csrfToken = $csrf->token();

                echo '<!DOCTYPE html>';
                echo '<html lang="es">';
                echo '<head>';
                echo '<meta charset="UTF-8">';
                echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
                echo '<title>Dashboard | NovaSysCore</title>';
                echo '</head>';

                echo '<body style="
                    font-family:system-ui,sans-serif;
                    padding:40px;
                    background:#f4f7fb;
                    color:#111827;
                ">';

                echo '<h1>NovaSysCore</h1>';

                echo '<p>Sesión iniciada correctamente.</p>';

                echo '<p>Bienvenido, <strong>'
                    . htmlspecialchars(
                        $displayName,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '</strong></p>';

                echo '<p>'
                    . htmlspecialchars(
                        $user['email'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '</p>';

                echo '<hr style="
                    margin:25px 0;
                    border:0;
                    border-top:1px solid #d1d5db;
                ">';

                echo '<p><strong>Contexto empresarial</strong></p>';

                echo '<p>Empresa: <strong>'
                    . htmlspecialchars(
                        $companyName,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '</strong></p>';

                echo '<p>Sucursal: <strong>'
                    . htmlspecialchars(
                        $branchName,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '</strong></p>';

                echo '
                    <form
                        method="POST"
                        action="'
                        . htmlspecialchars(
                            Url::to('/logout'),
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '"
                        style="margin-top:30px;"
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="'
                            . htmlspecialchars(
                                $csrfToken,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            . '"
                        >

                        <button type="submit">
                            Cerrar sesión
                        </button>
                    </form>
                ';

                echo '</body>';
                echo '</html>';
            },
            [
                AuthMiddleware::class,
                CompanyContextMiddleware::class,
            ]
        );

        /*
         * =====================================================
         * DESPACHAR PETICIÓN REAL
         * =====================================================
         */

        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        ob_start();

        $router->dispatch(
            $uri,
            $method
        );

        return (string) ob_get_clean();
    }
}