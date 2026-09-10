<?php

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Context\CompanyContextSelector;
use NovaSysCore\Database;

$pdo = Database::connection();

$tests = [];

function addSelectorTest(
    array &$tests,
    string $name,
    bool $result
): void {
    $tests[] = [
        'name' => $name,
        'result' => $result,
    ];
}

function selectorGetId(
    \PDO $pdo,
    string $sql,
    array $parameters = []
): int {
    $statement = $pdo->prepare($sql);

    $statement->execute(
        $parameters
    );

    $id = $statement->fetchColumn();

    if ($id === false) {
        throw new \RuntimeException(
            'No fue posible localizar un dato requerido.'
        );
    }

    return (int) $id;
}

function containsCompany(
    array $companies,
    int $companyId
): bool {
    foreach ($companies as $company) {
        if (
            isset($company['id'])
            && (int) $company['id']
                === $companyId
        ) {
            return true;
        }
    }

    return false;
}

/*
 * =========================================================
 * DATOS BASE
 * =========================================================
 */

$companyAlphaId = selectorGetId(
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

$companyBetaId = selectorGetId(
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

$superAdminRoleId = selectorGetId(
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

try {
    $pdo->beginTransaction();

    /*
     * =====================================================
     * USUARIO NORMAL
     * =====================================================
     */

    $normalEmail =
        'context.selector.normal@novasyscore.local';

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
        'last_name' => 'Selector Normal',
        'email' => $normalEmail,
        'password_hash' => password_hash(
            'Test1234!',
            PASSWORD_DEFAULT
        ),
    ]);

    $normalUserId = selectorGetId(
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
     * Eliminamos cualquier privilegio global
     * heredado de una ejecución anterior.
     */
    $statement = $pdo->prepare("
        DELETE FROM user_system_roles
        WHERE user_id = :user_id
    ");

    $statement->execute([
        'user_id' => $normalUserId,
    ]);

    /*
     * Reiniciamos membresías del usuario de prueba.
     */
    $statement = $pdo->prepare("
        DELETE FROM user_companies
        WHERE user_id = :user_id
    ");

    $statement->execute([
        'user_id' => $normalUserId,
    ]);

    /*
     * Solo Empresa Alpha.
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
    ");

    $statement->execute([
        'user_id' => $normalUserId,
        'company_id' => $companyAlphaId,
    ]);

    $normalMembershipId =
        (int) $pdo->lastInsertId();

    $selector =
        new CompanyContextSelector();

    /*
     * =====================================================
     * 1. ID INVÁLIDO
     * =====================================================
     */

    addSelectorTest(
        $tests,
        'ID de usuario invalido no devuelve empresas',
        $selector->availableCompanies(0)
            === []
    );

    /*
     * =====================================================
     * 2. USUARIO NORMAL SOLO VE SUS EMPRESAS
     * =====================================================
     */

    $companies =
        $selector->availableCompanies(
            $normalUserId
        );

    addSelectorTest(
        $tests,
        'Usuario normal puede ver Empresa Alpha',
        containsCompany(
            $companies,
            $companyAlphaId
        )
    );

    addSelectorTest(
        $tests,
        'Usuario normal no puede ver Empresa Beta sin membresia',
        !containsCompany(
            $companies,
            $companyBetaId
        )
    );

    addSelectorTest(
        $tests,
        'Usuario normal solo recibe una empresa activa',
        count($companies) === 1
    );

    /*
     * =====================================================
     * 3. MEMBRESÍA INACTIVA
     * =====================================================
     */

    $statement = $pdo->prepare("
        UPDATE user_companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $normalMembershipId,
    ]);

    $companies =
        $selector->availableCompanies(
            $normalUserId
        );

    addSelectorTest(
        $tests,
        'Membresia inactiva no permite seleccionar empresa',
        !containsCompany(
            $companies,
            $companyAlphaId
        )
    );

    /*
     * Restauramos membresía.
     */
    $statement = $pdo->prepare("
        UPDATE user_companies
        SET status = 'active'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $normalMembershipId,
    ]);

    /*
     * =====================================================
     * 4. EMPRESA INACTIVA
     * =====================================================
     */

    $statement = $pdo->prepare("
        UPDATE companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $companyAlphaId,
    ]);

    $companies =
        $selector->availableCompanies(
            $normalUserId
        );

    addSelectorTest(
        $tests,
        'Empresa inactiva no aparece aunque exista membresia',
        !containsCompany(
            $companies,
            $companyAlphaId
        )
    );

    /*
     * Restauramos Empresa Alpha.
     */
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
     * 5. USUARIO INACTIVO
     * =====================================================
     */

    $statement = $pdo->prepare("
        UPDATE users
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $normalUserId,
    ]);

    addSelectorTest(
        $tests,
        'Usuario inactivo no recibe empresas',
        $selector->availableCompanies(
            $normalUserId
        ) === []
    );

    $statement = $pdo->prepare("
        UPDATE users
        SET status = 'active'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $normalUserId,
    ]);

    /*
     * =====================================================
     * SUPER ADMINISTRATOR
     * =====================================================
     */

    $superEmail =
        'context.selector.superadmin@novasyscore.local';

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
        'last_name' => 'Selector Super',
        'email' => $superEmail,
        'password_hash' => password_hash(
            'Test1234!',
            PASSWORD_DEFAULT
        ),
    ]);

    $superUserId = selectorGetId(
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
     * Super Admin sin membresías empresariales.
     */
    $statement = $pdo->prepare("
        DELETE FROM user_companies
        WHERE user_id = :user_id
    ");

    $statement->execute([
        'user_id' => $superUserId,
    ]);

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

    $companies =
        $selector->availableCompanies(
            $superUserId
        );

    addSelectorTest(
        $tests,
        'Super Administrator puede ver Empresa Alpha sin membresia',
        containsCompany(
            $companies,
            $companyAlphaId
        )
    );

    addSelectorTest(
        $tests,
        'Super Administrator puede ver Empresa Beta sin membresia',
        containsCompany(
            $companies,
            $companyBetaId
        )
    );

    /*
     * =====================================================
     * 6. SUPER ADMIN NO DEBE VER EMPRESAS INACTIVAS
     * =====================================================
     */

    $statement = $pdo->prepare("
        UPDATE companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $companyBetaId,
    ]);

    $companies =
        $selector->availableCompanies(
            $superUserId
        );

    addSelectorTest(
        $tests,
        'Super Administrator no puede seleccionar empresa inactiva',
        !containsCompany(
            $companies,
            $companyBetaId
        )
    );

    addSelectorTest(
        $tests,
        'Super Administrator sigue viendo empresas activas',
        containsCompany(
            $companies,
            $companyAlphaId
        )
    );

    /*
     * Restauramos Empresa Beta.
     */
    $statement = $pdo->prepare("
        UPDATE companies
        SET status = 'active'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $companyBetaId,
    ]);

    /*
     * =====================================================
     * 7. SUPER ADMIN INACTIVO
     * =====================================================
     */

    $statement = $pdo->prepare("
        UPDATE users
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $superUserId,
    ]);

    addSelectorTest(
        $tests,
        'Super Administrator inactivo no recibe empresas',
        $selector->availableCompanies(
            $superUserId
        ) === []
    );

    /*
     * =====================================================
     * ROLLBACK
     * =====================================================
     */

    $pdo->rollBack();
} catch (\Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

/*
 * =========================================================
 * RESULTADOS
 * =========================================================
 */

echo PHP_EOL;

echo 'NovaSysCore - Company Context Selector Test'
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
        '[%s] %s',
        $status,
        $test['name']
    ) . PHP_EOL;
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