<?php

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Auth\SessionManager;
use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Database;
use NovaSysCore\Http\Middleware\CompanyContextMiddleware;

$pdo = Database::connection();

$tests = [];

function addTest(
    array &$tests,
    string $name,
    bool $result
): void {
    $tests[] = [
        'name' => $name,
        'result' => $result,
    ];
}

function getId(
    \PDO $pdo,
    string $sql,
    array $parameters
): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    $id = $statement->fetchColumn();

    if ($id === false) {
        throw new \RuntimeException(
            'No fue posible localizar un dato requerido para la prueba.'
        );
    }

    return (int) $id;
}

function resetTestSession(): void
{
    if (
        session_status()
        === PHP_SESSION_ACTIVE
    ) {
        $_SESSION = [];
        session_destroy();
    }

    $_SESSION = [];

    CompanyContextStore::clear();

    http_response_code(200);
}

function executeMiddleware(
    callable $next
): array {
    $middleware =
        new CompanyContextMiddleware();

    http_response_code(200);

    ob_start();

    $middleware->handle($next);

    $output = ob_get_clean();

    return [
        'status' =>
            http_response_code(),
        'output' =>
            $output,
    ];
}

/*
 * =========================================================
 * DATOS BASE
 * =========================================================
 */

$companyAlphaId = getId(
    $pdo,
    "
        SELECT id
        FROM companies
        WHERE slug = :slug
        LIMIT 1
    ",
    [
        'slug' => 'empresa-alpha',
    ]
);

$companyBetaId = getId(
    $pdo,
    "
        SELECT id
        FROM companies
        WHERE slug = :slug
        LIMIT 1
    ",
    [
        'slug' => 'empresa-beta',
    ]
);

$branchCentroId = getId(
    $pdo,
    "
        SELECT id
        FROM branches
        WHERE company_id = :company_id
          AND slug = :slug
        LIMIT 1
    ",
    [
        'company_id' => $companyAlphaId,
        'slug' => 'sucursal-centro',
    ]
);

$branchBetaId = getId(
    $pdo,
    "
        SELECT id
        FROM branches
        WHERE company_id = :company_id
          AND slug = :slug
        LIMIT 1
    ",
    [
        'company_id' => $companyBetaId,
        'slug' => 'sucursal-beta',
    ]
);

$superAdminRoleId = getId(
    $pdo,
    "
        SELECT id
        FROM roles
        WHERE company_id IS NULL
          AND slug = :slug
          AND is_system = TRUE
          AND status = 'active'
        LIMIT 1
    ",
    [
        'slug' => 'super-administrator',
    ]
);

/*
 * =========================================================
 * TODO EL ESCENARIO DE PRUEBA VIVE EN UNA TRANSACCIÓN
 * =========================================================
 */

try {
    $pdo->beginTransaction();

    /*
     * =====================================================
     * USUARIO EMPRESARIAL NORMAL
     * =====================================================
     */

    $normalEmail =
        'context.middleware.normal@novasyscore.local';

    $statement = $pdo->prepare("
        INSERT INTO users (
            name,
            last_name,
            email,
            password_hash,
            status
        ) VALUES (
            :name,
            :last_name,
            :email,
            :password_hash,
            'active'
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            last_name = VALUES(last_name),
            status = 'active'
    ");

    $statement->execute([
        'name' => 'Context',
        'last_name' => 'Middleware Normal',
        'email' => $normalEmail,
        'password_hash' => password_hash(
            'Test1234!',
            PASSWORD_DEFAULT
        ),
    ]);

    $normalUserId = getId(
        $pdo,
        "
            SELECT id
            FROM users
            WHERE email = :email
            LIMIT 1
        ",
        [
            'email' => $normalEmail,
        ]
    );

    /*
     * Garantizamos que sea un usuario normal,
     * sin privilegios globales.
     */
    $statement = $pdo->prepare("
        DELETE FROM user_system_roles
        WHERE user_id = :user_id
    ");

    $statement->execute([
        'user_id' => $normalUserId,
    ]);

    /*
     * Solo pertenece a Empresa Alpha.
     */
    $statement = $pdo->prepare("
        INSERT INTO user_companies (
            user_id,
            company_id,
            status
        ) VALUES (
            :user_id,
            :company_id,
            'active'
        )
        ON DUPLICATE KEY UPDATE
            status = 'active'
    ");

    $statement->execute([
        'user_id' => $normalUserId,
        'company_id' => $companyAlphaId,
    ]);

    $statement = $pdo->prepare("
        DELETE FROM user_companies
        WHERE user_id = :user_id
          AND company_id = :company_id
    ");

    $statement->execute([
        'user_id' => $normalUserId,
        'company_id' => $companyBetaId,
    ]);

    $normalUserCompanyId = getId(
        $pdo,
        "
            SELECT id
            FROM user_companies
            WHERE user_id = :user_id
              AND company_id = :company_id
            LIMIT 1
        ",
        [
            'user_id' => $normalUserId,
            'company_id' => $companyAlphaId,
        ]
    );

    /*
     * =====================================================
     * 1. USUARIO NO AUTENTICADO
     * =====================================================
     */

    resetTestSession();

    $nextExecuted = false;

    $result = executeMiddleware(
        function () use (
            &$nextExecuted
        ): void {
            $nextExecuted = true;
        }
    );

    addTest(
        $tests,
        'Rechaza una peticion sin usuario autenticado',
        $result['status'] === 403
            && $nextExecuted === false
    );

    addTest(
        $tests,
        'No deja contexto al rechazar usuario anonimo',
        CompanyContextStore::has() === false
    );

    /*
     * =====================================================
     * 2. USUARIO SIN EMPRESA SELECCIONADA
     * =====================================================
     */

    resetTestSession();

    $session = new SessionManager();
    $session->login($normalUserId);

    $nextExecuted = false;

    $result = executeMiddleware(
        function () use (
            &$nextExecuted
        ): void {
            $nextExecuted = true;
        }
    );

    addTest(
        $tests,
        'Rechaza usuario autenticado sin empresa seleccionada',
        $result['status'] === 403
            && $nextExecuted === false
    );

    /*
     * =====================================================
     * 3. CONTEXTO EMPRESARIAL VÁLIDO
     * =====================================================
     */

    resetTestSession();

    $session = new SessionManager();
    $session->login($normalUserId);

    $session->setCompanyContext(
        $companyAlphaId,
        $branchCentroId
    );

    $nextExecuted = false;
    $contextVisibleInsideNext = false;

    $result = executeMiddleware(
        function () use (
            &$nextExecuted,
            &$contextVisibleInsideNext,
            $normalUserId,
            $companyAlphaId,
            $branchCentroId
        ): void {
            $nextExecuted = true;

            $context =
                CompanyContextStore::get();

            $contextVisibleInsideNext =
                $context !== null
                && $context->userId()
                    === $normalUserId
                && $context->companyId()
                    === $companyAlphaId
                && $context->branchId()
                    === $branchCentroId;
        }
    );

    addTest(
        $tests,
        'Permite un contexto empresarial valido',
        $result['status'] === 200
            && $nextExecuted === true
    );

    addTest(
        $tests,
        'El contexto validado esta disponible durante next',
        $contextVisibleInsideNext === true
    );

    addTest(
        $tests,
        'El contexto se limpia despues de ejecutar next',
        CompanyContextStore::has() === false
    );

    /*
     * =====================================================
     * 4. MEMBRESÍA REVOCADA DESPUÉS DE GUARDAR SESIÓN
     * =====================================================
     */

    resetTestSession();

    $session = new SessionManager();
    $session->login($normalUserId);

    $session->setCompanyContext(
        $companyAlphaId,
        $branchCentroId
    );

    /*
     * Simulamos que un administrador revoca
     * el acceso mientras la sesión sigue activa.
     */
    $statement = $pdo->prepare("
        UPDATE user_companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $normalUserCompanyId,
    ]);

    $nextExecuted = false;

    $result = executeMiddleware(
        function () use (
            &$nextExecuted
        ): void {
            $nextExecuted = true;
        }
    );

    addTest(
        $tests,
        'Rechaza contexto si la membresia fue revocada',
        $result['status'] === 403
            && $nextExecuted === false
    );

    addTest(
        $tests,
        'Elimina de sesion un contexto cuya membresia ya no es valida',
        $session->companyContextId() === null
            && $session->branchContextId()
                === null
    );

    /*
     * Restauramos membresía para continuar.
     */
    $statement = $pdo->prepare("
        UPDATE user_companies
        SET status = 'active'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $normalUserCompanyId,
    ]);

    /*
     * =====================================================
     * 5. EMPRESA DESACTIVADA
     * =====================================================
     */

    resetTestSession();

    $session = new SessionManager();
    $session->login($normalUserId);

    $session->setCompanyContext(
        $companyAlphaId
    );

    $statement = $pdo->prepare("
        UPDATE companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $companyAlphaId,
    ]);

    $nextExecuted = false;

    $result = executeMiddleware(
        function () use (
            &$nextExecuted
        ): void {
            $nextExecuted = true;
        }
    );

    addTest(
        $tests,
        'Rechaza una empresa desactivada despues de seleccionarla',
        $result['status'] === 403
            && $nextExecuted === false
    );

    addTest(
        $tests,
        'Elimina de sesion una empresa que dejo de ser valida',
        $session->companyContextId() === null
    );

    $statement = $pdo->prepare("
        UPDATE companies
        SET status = 'active'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $companyAlphaId,
    ]);

    /*
     * =====================================================
     * 6. SUCURSAL DE OTRA EMPRESA
     * =====================================================
     */

    resetTestSession();

    $session = new SessionManager();
    $session->login($normalUserId);

    /*
     * La sesión puede contener datos manipulados.
     * El middleware debe volverlos a validar.
     */
    $session->setCompanyContext(
        $companyAlphaId,
        $branchBetaId
    );

    $nextExecuted = false;

    $result = executeMiddleware(
        function () use (
            &$nextExecuted
        ): void {
            $nextExecuted = true;
        }
    );

    addTest(
        $tests,
        'Rechaza sucursal que pertenece a otra empresa',
        $result['status'] === 403
            && $nextExecuted === false
    );

    addTest(
        $tests,
        'Limpia de sesion el contexto manipulado',
        $session->companyContextId() === null
            && $session->branchContextId()
                === null
    );

    /*
     * =====================================================
     * 7. SUPER ADMINISTRATOR
     * =====================================================
     */

    $superEmail =
        'context.middleware.superadmin@novasyscore.local';

    $statement = $pdo->prepare("
        INSERT INTO users (
            name,
            last_name,
            email,
            password_hash,
            status
        ) VALUES (
            :name,
            :last_name,
            :email,
            :password_hash,
            'active'
        )
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            last_name = VALUES(last_name),
            status = 'active'
    ");

    $statement->execute([
        'name' => 'Context',
        'last_name' => 'Middleware Super',
        'email' => $superEmail,
        'password_hash' => password_hash(
            'Test1234!',
            PASSWORD_DEFAULT
        ),
    ]);

    $superUserId = getId(
        $pdo,
        "
            SELECT id
            FROM users
            WHERE email = :email
            LIMIT 1
        ",
        [
            'email' => $superEmail,
        ]
    );

    /*
     * Dejamos al usuario sin membresías empresariales.
     */
    $statement = $pdo->prepare("
        DELETE FROM user_companies
        WHERE user_id = :user_id
    ");

    $statement->execute([
        'user_id' => $superUserId,
    ]);

    /*
     * Dejamos únicamente el rol global necesario.
     */
    $statement = $pdo->prepare("
        DELETE FROM user_system_roles
        WHERE user_id = :user_id
    ");

    $statement->execute([
        'user_id' => $superUserId,
    ]);

    $statement = $pdo->prepare("
        INSERT INTO user_system_roles (
            user_id,
            role_id,
            created_at,
            updated_at
        ) VALUES (
            :user_id,
            :role_id,
            NOW(),
            NOW()
        )
    ");

    $statement->execute([
        'user_id' => $superUserId,
        'role_id' => $superAdminRoleId,
    ]);

    resetTestSession();

    $session = new SessionManager();
    $session->login($superUserId);

    $session->setCompanyContext(
        $companyBetaId,
        $branchBetaId
    );

    $nextExecuted = false;
    $superContextValid = false;

    $result = executeMiddleware(
        function () use (
            &$nextExecuted,
            &$superContextValid,
            $superUserId,
            $companyBetaId,
            $branchBetaId
        ): void {
            $nextExecuted = true;

            $context =
                CompanyContextStore::get();

            $superContextValid =
                $context !== null
                && $context->userId()
                    === $superUserId
                && $context->companyId()
                    === $companyBetaId
                && $context->branchId()
                    === $branchBetaId;
        }
    );

    addTest(
        $tests,
        'Super Administrator resuelve empresa sin membresia',
        $result['status'] === 200
            && $nextExecuted === true
            && $superContextValid === true
    );

    addTest(
        $tests,
        'El store tambien se limpia despues de Super Administrator',
        CompanyContextStore::has() === false
    );

    /*
     * =====================================================
     * LIMPIEZA DE BD
     * =====================================================
     */

    $pdo->rollBack();
} catch (\Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    resetTestSession();

    throw $exception;
}

/*
 * =========================================================
 * LIMPIEZA DE SESIÓN
 * =========================================================
 */

resetTestSession();

/*
 * =========================================================
 * RESULTADOS
 * =========================================================
 */

echo PHP_EOL;

echo 'NovaSysCore - Company Context Middleware Test'
    . PHP_EOL;

echo str_repeat(
    '=',
    78
) . PHP_EOL;

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    if ($test['result']) {
        $passed++;
        $status = 'OK';
    } else {
        $failed++;
        $status = 'FAIL';
    }

    echo sprintf(
        "[%s] %s",
        $status,
        $test['name']
    );

    echo PHP_EOL;
}

echo str_repeat(
    '-',
    78
) . PHP_EOL;

echo "Correctas: {$passed}"
    . PHP_EOL;

echo "Fallidas:  {$failed}"
    . PHP_EOL;

echo PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);