<?php

namespace NovaSysCore;

use App\Http\Controllers\Auth\LoginController;
use NovaSysCore\Auth\Auth;
use NovaSysCore\Url;
use NovaSysCore\Security\CsrfTokenManager;
use NovaSysCore\Http\Middleware\AuthMiddleware;

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
         * RUTA INICIAL
         * =====================================================
         */

        $router->get('/', function (): void {

            if (Auth::check()) {
                header(
                    'Location: ' . Url::to('/dashboard')
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