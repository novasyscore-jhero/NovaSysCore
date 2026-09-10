<?php

require dirname(__DIR__, 2)
    . '/bootstrap/app.php';

use NovaSysCore\Context\CompanyContextResolver;
use NovaSysCore\Database;

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

$branchNorteId = getId(
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
        'slug' => 'sucursal-norte',
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

/*
 * =========================================================
 * USUARIO NORMAL EXCLUSIVO PARA ESTA PRUEBA
 * =========================================================
 */

$normalEmail =
    'context.normal.test@novasyscore.local';

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
    'last_name' => 'Normal Test',
    'email' => $normalEmail,
    'password_hash' => password_hash(
        'Test1234!',
        PASSWORD_DEFAULT
    ),
]);

$userId = getId(
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
 * Garantizamos que este usuario sea realmente normal
 * aunque otra prueba se haya ejecutado anteriormente.
 */
$statement = $pdo->prepare("
    DELETE FROM user_system_roles
    WHERE user_id = :user_id
");

$statement->execute([
    'user_id' => $userId,
]);

/*
 * El usuario pertenece únicamente a Empresa Alpha.
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
    'user_id' => $userId,
    'company_id' => $companyAlphaId,
]);

/*
 * Eliminamos cualquier membresía accidental en Beta.
 */
$statement = $pdo->prepare("
    DELETE FROM user_companies
    WHERE user_id = :user_id
      AND company_id = :company_id
");

$statement->execute([
    'user_id' => $userId,
    'company_id' => $companyBetaId,
]);

$userCompanyAlphaId = getId(
    $pdo,
    "
        SELECT id
        FROM user_companies
        WHERE user_id = :user_id
          AND company_id = :company_id
        LIMIT 1
    ",
    [
        'user_id' => $userId,
        'company_id' => $companyAlphaId,
    ]
);

$resolver = new CompanyContextResolver();

/*
 * =========================================================
 * CONTEXTO EMPRESARIAL VÁLIDO
 * =========================================================
 */

$context = $resolver->resolve(
    $userId,
    $companyAlphaId
);

addTest(
    $tests,
    'Resuelve usuario activo con empresa autorizada',
    $context !== null
);

addTest(
    $tests,
    'El contexto conserva el usuario correcto',
    $context !== null
        && $context->userId() === $userId
);

addTest(
    $tests,
    'El contexto conserva la empresa correcta',
    $context !== null
        && $context->companyId() === $companyAlphaId
);

addTest(
    $tests,
    'El contexto empresarial puede existir sin sucursal',
    $context !== null
        && !$context->hasBranch()
        && $context->branchId() === null
);

/*
 * =========================================================
 * SUCURSALES VÁLIDAS
 * =========================================================
 */

$contextCentro = $resolver->resolve(
    $userId,
    $companyAlphaId,
    $branchCentroId
);

addTest(
    $tests,
    'Resuelve una sucursal activa de la empresa',
    $contextCentro !== null
);

addTest(
    $tests,
    'El contexto conserva la sucursal correcta',
    $contextCentro !== null
        && $contextCentro->branchId()
            === $branchCentroId
);

$contextNorte = $resolver->resolve(
    $userId,
    $companyAlphaId,
    $branchNorteId
);

addTest(
    $tests,
    'El resolver no duplica reglas de branch_scope',
    $contextNorte !== null
);

/*
 * =========================================================
 * CONTEXTOS INVÁLIDOS
 * =========================================================
 */

addTest(
    $tests,
    'Rechaza una sucursal perteneciente a otra empresa',
    $resolver->resolve(
        $userId,
        $companyAlphaId,
        $branchBetaId
    ) === null
);

addTest(
    $tests,
    'Rechaza una empresa donde el usuario no tiene membresia',
    $resolver->resolve(
        $userId,
        $companyBetaId
    ) === null
);

addTest(
    $tests,
    'Rechaza una empresa inexistente',
    $resolver->resolve(
        $userId,
        PHP_INT_MAX
    ) === null
);

addTest(
    $tests,
    'Rechaza un usuario inexistente',
    $resolver->resolve(
        PHP_INT_MAX,
        $companyAlphaId
    ) === null
);

addTest(
    $tests,
    'Rechaza IDs no validos',
    $resolver->resolve(
        0,
        $companyAlphaId
    ) === null
        && $resolver->resolve(
            $userId,
            0
        ) === null
        && $resolver->resolve(
            $userId,
            $companyAlphaId,
            0
        ) === null
);

/*
 * =========================================================
 * CAMBIOS TEMPORALES
 * =========================================================
 */

try {
    $pdo->beginTransaction();

    /*
     * Membresía inactiva.
     */
    $statement = $pdo->prepare("
        UPDATE user_companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $userCompanyAlphaId,
    ]);

    addTest(
        $tests,
        'Rechaza una membresia inactiva',
        $resolver->resolve(
            $userId,
            $companyAlphaId
        ) === null
    );

    /*
     * Restauramos temporalmente la membresía.
     */
    $statement = $pdo->prepare("
        UPDATE user_companies
        SET status = 'active'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $userCompanyAlphaId,
    ]);

    /*
     * Empresa inactiva.
     */
    $statement = $pdo->prepare("
        UPDATE companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $companyAlphaId,
    ]);

    addTest(
        $tests,
        'Rechaza una empresa inactiva',
        $resolver->resolve(
            $userId,
            $companyAlphaId
        ) === null
    );

    /*
     * Restauramos temporalmente la empresa.
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
     * Sucursal inactiva.
     */
    $statement = $pdo->prepare("
        UPDATE branches
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $branchCentroId,
    ]);

    addTest(
        $tests,
        'Rechaza una sucursal inactiva',
        $resolver->resolve(
            $userId,
            $companyAlphaId,
            $branchCentroId
        ) === null
    );

    $pdo->rollBack();
} catch (\Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

/*
 * =========================================================
 * COMPROBACIÓN POST-ROLLBACK
 * =========================================================
 */

addTest(
    $tests,
    'El rollback conserva intacto el escenario original',
    $resolver->resolve(
        $userId,
        $companyAlphaId,
        $branchCentroId
    ) !== null
);

/*
 * =========================================================
 * SUPER ADMINISTRATOR GLOBAL
 * =========================================================
 */

try {
    $pdo->beginTransaction();

    $superEmail =
        'context.superadmin.test@novasyscore.local';

    /*
     * Limpiamos un posible residuo anterior dentro de
     * esta misma transacción.
     */
    $statement = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

    $statement->execute([
        'email' => $superEmail,
    ]);

    $existingSuperId =
        $statement->fetchColumn();

    if ($existingSuperId !== false) {
        $statement = $pdo->prepare("
            DELETE FROM user_system_roles
            WHERE user_id = :user_id
        ");

        $statement->execute([
            'user_id' => (int) $existingSuperId,
        ]);

        $statement = $pdo->prepare("
            DELETE FROM user_companies
            WHERE user_id = :user_id
        ");

        $statement->execute([
            'user_id' => (int) $existingSuperId,
        ]);

        $statement = $pdo->prepare("
            DELETE FROM users
            WHERE id = :user_id
        ");

        $statement->execute([
            'user_id' => (int) $existingSuperId,
        ]);
    }

    /*
     * Creamos un Super Administrator temporal.
     */
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
    ");

    $statement->execute([
        'name' => 'Super',
        'last_name' => 'Context Test',
        'email' => $superEmail,
        'password_hash' => password_hash(
            'Test1234!',
            PASSWORD_DEFAULT
        ),
    ]);

    $superAdminUserId =
        (int) $pdo->lastInsertId();

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
        'user_id' => $superAdminUserId,
        'role_id' => $superAdminRoleId,
    ]);

    /*
     * Deliberadamente NO creamos user_companies.
     */

    $superContextAlpha = $resolver->resolve(
        $superAdminUserId,
        $companyAlphaId
    );

    addTest(
        $tests,
        'Super Administrator accede a empresa sin membresia',
        $superContextAlpha !== null
            && $superContextAlpha->companyId()
                === $companyAlphaId
    );

    $superContextBeta = $resolver->resolve(
        $superAdminUserId,
        $companyBetaId
    );

    addTest(
        $tests,
        'Super Administrator puede resolver otra empresa activa',
        $superContextBeta !== null
            && $superContextBeta->companyId()
                === $companyBetaId
    );

    $superBranchBeta = $resolver->resolve(
        $superAdminUserId,
        $companyBetaId,
        $branchBetaId
    );

    addTest(
        $tests,
        'Super Administrator puede resolver sucursal valida',
        $superBranchBeta !== null
            && $superBranchBeta->branchId()
                === $branchBetaId
    );

    addTest(
        $tests,
        'Super Administrator no puede usar sucursal de otra empresa',
        $resolver->resolve(
            $superAdminUserId,
            $companyAlphaId,
            $branchBetaId
        ) === null
    );

    addTest(
        $tests,
        'Super Administrator no puede resolver empresa inexistente',
        $resolver->resolve(
            $superAdminUserId,
            PHP_INT_MAX
        ) === null
    );

    /*
     * Ni siquiera Super Administrator puede convertir
     * una empresa inactiva en contexto operativo.
     */
    $statement = $pdo->prepare("
        UPDATE companies
        SET status = 'inactive'
        WHERE id = :id
    ");

    $statement->execute([
        'id' => $companyBetaId,
    ]);

    addTest(
        $tests,
        'Super Administrator no puede resolver empresa inactiva',
        $resolver->resolve(
            $superAdminUserId,
            $companyBetaId
        ) === null
    );

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

echo 'NovaSysCore - Company Context Resolver Test'
    . PHP_EOL;

echo str_repeat('=', 78)
    . PHP_EOL;

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

echo str_repeat('-', 78)
    . PHP_EOL;

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