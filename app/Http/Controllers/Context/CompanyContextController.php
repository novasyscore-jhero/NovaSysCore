<?php

namespace App\Http\Controllers\Context;

use NovaSysCore\Auth\SessionManager;
use NovaSysCore\Context\BranchContextSelector;
use NovaSysCore\Context\CompanyContextResolver;
use NovaSysCore\Context\CompanyContextSelector;
use NovaSysCore\Security\CsrfTokenManager;
use NovaSysCore\Url;

class CompanyContextController
{
    private SessionManager $session;
    private CompanyContextSelector $companySelector;
    private BranchContextSelector $branchSelector;
    private CompanyContextResolver $resolver;

    public function __construct()
    {
        $this->session =
            new SessionManager();

        $this->companySelector =
            new CompanyContextSelector();

        $this->branchSelector =
            new BranchContextSelector();

        $this->resolver =
            new CompanyContextResolver();
    }

    /**
     * Entrada principal al contexto empresarial.
     */
    public function index(): void
    {
        $userId =
            $this->session->userId();

        if ($userId === null) {
            $this->deny();

            return;
        }

        $companies =
            $this->companySelector
                ->availableCompanies($userId);

        /*
         * Sin empresas disponibles.
         */
        if (count($companies) === 0) {
            $this->session
                ->clearCompanyContext();

            require dirname(__DIR__, 3)
                . '/Views/context/unavailable.php';

            return;
        }

        /*
         * Una sola empresa:
         * no mostramos selector innecesariamente.
         */
        if (count($companies) === 1) {
            $companyId =
                (int) $companies[0]['id'];

            $this->continueWithCompany(
                $userId,
                $companyId
            );

            return;
        }

        /*
         * Varias empresas.
         */
        $csrf =
            new CsrfTokenManager();

        $csrfToken =
            $csrf->token();

        require dirname(__DIR__, 3)
            . '/Views/context/select.php';
    }

    /**
     * Recibe la empresa elegida.
     */
    public function select(): void
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            !== 'POST'
        ) {
            $this->methodNotAllowed();

            return;
        }

        $userId =
            $this->session->userId();

        if ($userId === null) {
            $this->deny();

            return;
        }

        $companyId =
            $this->postPositiveInt(
                'company_id'
            );

        if ($companyId === null) {
            $this->deny();

            return;
        }

        /*
         * Nunca confiamos en el company_id
         * recibido desde el formulario.
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

        $this->continueWithCompany(
            $userId,
            $companyId
        );
    }

    /**
     * Muestra/resuelve las sucursales disponibles
     * para la empresa que ya está en sesión.
     */
    public function branch(): void
    {
        $userId =
            $this->session->userId();

        $companyId =
            $this->session->companyContextId();

        if (
            $userId === null
            || $companyId === null
        ) {
            $this->session
                ->clearCompanyContext();

            $this->redirect(
                Url::to('/context')
            );
        }

        /*
         * Revalidamos la empresa antes de trabajar
         * con las sucursales.
         */
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

        $this->resolveBranches(
            $userId,
            $companyId
        );
    }

    /**
     * Recibe la sucursal elegida.
     */
    public function selectBranch(): void
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            !== 'POST'
        ) {
            $this->methodNotAllowed();

            return;
        }

        $userId =
            $this->session->userId();

        $companyId =
            $this->session->companyContextId();

        if (
            $userId === null
            || $companyId === null
        ) {
            $this->deny();

            return;
        }

        $branchId =
            $this->postPositiveInt(
                'branch_id'
            );

        if ($branchId === null) {
            $this->deny();

            return;
        }

        /*
         * Primer control:
         * comprobar que la sucursal esté dentro
         * del ámbito permitido del usuario.
         */
        $branches =
            $this->branchSelector
                ->availableBranches(
                    $userId,
                    $companyId
                );

        $allowed = false;

        foreach ($branches as $branch) {
            if (
                (int) $branch['id']
                === $branchId
            ) {
                $allowed = true;

                break;
            }
        }

        if (!$allowed) {
            $this->deny();

            return;
        }

        /*
         * Segundo control:
         * validar formalmente empresa + sucursal.
         */
        $context =
            $this->resolver->resolve(
                $userId,
                $companyId,
                $branchId
            );

        if ($context === null) {
            $this->deny();

            return;
        }

        $this->session
            ->setCompanyContext(
                $companyId,
                $branchId
            );

        $this->redirect(
            Url::to('/dashboard')
        );
    }

    /**
     * Continúa el flujo después de validar empresa.
     */
    private function continueWithCompany(
        int $userId,
        int $companyId
    ): void {
        /*
         * Guardamos primero contexto a nivel empresa.
         *
         * La sucursal todavía no está elegida.
         */
        $this->session
            ->setCompanyContext(
                $companyId,
                null
            );

        $this->resolveBranches(
            $userId,
            $companyId
        );
    }

    /**
     * Resuelve automáticamente 0, 1 o varias
     * sucursales disponibles.
     */
    private function resolveBranches(
        int $userId,
        int $companyId
    ): void {
        $branches =
            $this->branchSelector
                ->availableBranches(
                    $userId,
                    $companyId
                );

        /*
         * Cero sucursales disponibles:
         * se permite contexto únicamente de empresa.
         */
        if (count($branches) === 0) {
            $this->session
                ->setCompanyContext(
                    $companyId,
                    null
                );

            $this->redirect(
                Url::to('/dashboard')
            );
        }

        /*
         * Una sola sucursal:
         * selección automática.
         */
        if (count($branches) === 1) {
            $branchId =
                (int) $branches[0]['id'];

            $context =
                $this->resolver->resolve(
                    $userId,
                    $companyId,
                    $branchId
                );

            if ($context === null) {
                $this->session
                    ->clearCompanyContext();

                $this->deny();

                return;
            }

            $this->session
                ->setCompanyContext(
                    $companyId,
                    $branchId
                );

            $this->redirect(
                Url::to('/dashboard')
            );
        }

        /*
         * Varias sucursales:
         * mostramos selector.
         */
        $csrf =
            new CsrfTokenManager();

        $csrfToken =
            $csrf->token();

        require dirname(__DIR__, 3)
            . '/Views/context/branch.php';
    }

    /**
     * Obtiene un entero positivo enviado por POST.
     */
    private function postPositiveInt(
        string $field
    ): ?int {
        $value =
            filter_input(
                INPUT_POST,
                $field,
                FILTER_VALIDATE_INT
            );

        /*
         * Fallback para CLI/tests.
         */
        if (
            $value === false
            || $value === null
        ) {
            $raw =
                $_POST[$field]
                ?? null;

            if (
                is_string($raw)
                && ctype_digit($raw)
            ) {
                $value =
                    (int) $raw;
            }
        }

        if (
            !is_int($value)
            || $value <= 0
        ) {
            return null;
        }

        return $value;
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);

        echo 'Método no permitido.';
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