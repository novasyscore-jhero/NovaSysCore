<?php

namespace App\Http\Controllers\Context;

use NovaSysCore\Auth\SessionManager;
use NovaSysCore\Context\CompanyContextResolver;
use NovaSysCore\Context\CompanyContextSelector;
use NovaSysCore\Security\CsrfTokenManager;
use NovaSysCore\Url;

class CompanyContextController
{
    private SessionManager $session;
    private CompanyContextSelector $selector;
    private CompanyContextResolver $resolver;

    public function __construct()
    {
        $this->session =
            new SessionManager();

        $this->selector =
            new CompanyContextSelector();

        $this->resolver =
            new CompanyContextResolver();
    }

    public function index(): void
    {
        $userId =
            $this->session->userId();

        if ($userId === null) {
            $this->deny();

            return;
        }

        $companies =
            $this->selector->availableCompanies(
                $userId
            );

        /*
         * =====================================================
         * SIN EMPRESAS DISPONIBLES
         * =====================================================
         */

        if (count($companies) === 0) {
            $this->session
                ->clearCompanyContext();

            require dirname(__DIR__, 3)
                . '/Views/context/unavailable.php';

            return;
        }

        /*
         * =====================================================
         * UNA SOLA EMPRESA
         * =====================================================
         *
         * No obligamos al usuario a realizar una selección
         * innecesaria.
         */

        if (count($companies) === 1) {
            $companyId =
                (int) $companies[0]['id'];

            $context =
                $this->resolver->resolve(
                    $userId,
                    $companyId
                );

            if ($context === null) {
                $this->session
                    ->clearCompanyContext();

                $this->deny();

                return;
            }

            $this->session
                ->setCompanyContext(
                    $companyId
                );

            $this->redirect(
                Url::to('/dashboard')
            );
        }

        /*
         * =====================================================
         * MÚLTIPLES EMPRESAS
         * =====================================================
         */

        $csrf =
            new CsrfTokenManager();

        $csrfToken =
            $csrf->token();

        require dirname(__DIR__, 3)
            . '/Views/context/select.php';
    }

    public function select(): void
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            !== 'POST'
        ) {
            http_response_code(405);

            echo 'Método no permitido';

            return;
        }

        $userId =
            $this->session->userId();

        if ($userId === null) {
            $this->deny();

            return;
        }

        $companyId =
            filter_input(
                INPUT_POST,
                'company_id',
                FILTER_VALIDATE_INT
            );

        /*
         * En pruebas CLI filter_input() puede no trabajar
         * igual que bajo Apache, por eso mantenemos un
         * fallback controlado sobre $_POST.
         */
        if ($companyId === false || $companyId === null) {
            $rawCompanyId =
                $_POST['company_id']
                ?? null;

            if (
                is_string($rawCompanyId)
                && ctype_digit($rawCompanyId)
            ) {
                $companyId =
                    (int) $rawCompanyId;
            }
        }

        if (
            !is_int($companyId)
            || $companyId <= 0
        ) {
            $this->deny();

            return;
        }

        /*
         * Nunca confiamos en el ID recibido desde
         * el formulario.
         */
        $context =
            $this->resolver->resolve(
                $userId,
                $companyId
            );

        if ($context === null) {
            $this->deny();

            return;
        }

        $this->session
            ->setCompanyContext(
                $companyId
            );

        $this->redirect(
            Url::to('/dashboard')
        );
    }

    private function deny(): void
    {
        http_response_code(403);

        echo 'No existe un contexto empresarial válido.';
    }

    private function redirect(
        string $location
    ): never {
        header(
            'Location: ' . $location
        );

        exit;
    }
}