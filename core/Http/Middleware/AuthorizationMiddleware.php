<?php

namespace NovaSysCore\Http\Middleware;

use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Security\AuthorizationService;

class AuthorizationMiddleware implements MiddlewareInterface
{
    private AuthorizationService $authorization;
    private string $permission;

    public function __construct(
        string $permission,
        ?AuthorizationService $authorization = null
    ) {
        $this->permission = trim($permission);

        $this->authorization =
            $authorization ?? new AuthorizationService();
    }

    public function handle(callable $next): void
    {
        $context = CompanyContextStore::get();

        if ($context === null) {
            $this->deny();
            return;
        }

        $allowed = $this->authorization->can(
            $context->userId(),
            $context->companyId(),
            $this->permission,
            $context->branchId()
        );

        if (!$allowed) {
            $this->deny();
            return;
        }

        $next();
    }

    private function deny(): void
    {
        http_response_code(403);

        echo 'No tienes permiso para realizar esta acción.';
    }
}