<?php

namespace App\Http\Controllers\Auth;

use NovaSysCore\Auth\Auth;
use NovaSysCore\Auth\AuthenticationService;
use NovaSysCore\Url;

class LoginController
{
    private AuthenticationService $authentication;

    public function __construct()
    {
        $this->authentication = new AuthenticationService();
    }

    public function show(): void
    {
        if (Auth::user() !== null) {
            $this->redirect(
                Url::to('/dashboard')
            );
        }

        $error = $_GET['error'] ?? null;

        require dirname(__DIR__, 3)
            . '/Views/auth/login.php';
    }

    public function login(): void
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
        ) {
            http_response_code(405);

            echo 'Método no permitido';

            return;
        }

        $email = isset($_POST['email'])
            ? (string) $_POST['email']
            : '';

        $password = isset($_POST['password'])
            ? (string) $_POST['password']
            : '';

        $authenticated = $this->authentication->attempt(
            $email,
            $password
        );

        if (!$authenticated) {
            $this->redirect(
                Url::to(
                    '/login?error=invalid_credentials'
                )
            );
        }

        $this->redirect(
            Url::to('/dashboard')
        );
    }

    public function logout(): void
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
        ) {
            http_response_code(405);

            echo 'Método no permitido';

            return;
        }

        if (Auth::check()) {
            $this->authentication->logout();
        }

        $this->redirect(
            Url::to('/login')
        );
    }

    private function redirect(string $location): never
    {
        header(
            'Location: ' . $location
        );

        exit;
    }
}