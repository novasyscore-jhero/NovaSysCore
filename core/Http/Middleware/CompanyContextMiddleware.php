<?php

namespace NovaSysCore\Http\Middleware;

use NovaSysCore\Auth\SessionManager;
use NovaSysCore\Context\CompanyContextResolver;
use NovaSysCore\Context\CompanyContextStore;

class CompanyContextMiddleware implements
    MiddlewareInterface
{
    public function handle(callable $next): void
    {
        /*
         * Siempre empezamos la petición sin
         * un contexto previamente resuelto.
         */
        CompanyContextStore::clear();

        $session = new SessionManager();

        $userId = $session->userId();

        /*
         * AuthMiddleware debe ejecutarse antes,
         * pero mantenemos esta defensa adicional.
         */
        if ($userId === null) {
            $this->deny();

            return;
        }

        $companyId =
            $session->companyContextId();

        $branchId =
            $session->branchContextId();

        /*
         * Sin empresa seleccionada todavía no existe
         * un contexto empresarial operativo.
         */
        if ($companyId === null) {
            $this->deny();

            return;
        }

        $resolver =
            new CompanyContextResolver();

        $context = $resolver->resolve(
            $userId,
            $companyId,
            $branchId
        );

        /*
         * La sesión puede contener una selección
         * antigua que ya dejó de ser válida:
         *
         * - membresía revocada,
         * - empresa desactivada,
         * - sucursal desactivada,
         * - sucursal cambiada de empresa, etc.
         *
         * Nunca confiamos únicamente en la sesión.
         */
        if ($context === null) {
            $session->clearCompanyContext();

            $this->deny();

            return;
        }

        CompanyContextStore::set(
            $context
        );

        try {
            $next();
        } finally {
            /*
             * Evita que un contexto sobreviva
             * accidentalmente dentro del mismo
             * proceso de pruebas o ejecución.
             */
            CompanyContextStore::clear();
        }
    }

    private function deny(): void
    {
        http_response_code(403);

        echo 'No existe un contexto empresarial válido.';
    }
}