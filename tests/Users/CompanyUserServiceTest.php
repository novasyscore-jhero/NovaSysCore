<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\Users\CompanyUserService;
use NovaSysCore\Database;

echo PHP_EOL;
echo "NovaSysCore - Company User Service Test" . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;

$correct = 0;
$failed = 0;

function checkCompanyUserService(
    string $label,
    bool $condition
): void {
    global $correct, $failed;

    if ($condition) {
        echo "[OK] {$label}" . PHP_EOL;
        $correct++;

        return;
    }

    echo "[FAIL] {$label}" . PHP_EOL;
    $failed++;
}

$pdo = Database::connection();

$temporaryEmail =
    'company-user-service-'
    . bin2hex(random_bytes(8))
    . '@novasyscore.local';

$userId = null;

try {
    $service = new CompanyUserService(
        $pdo
    );

    /*
     * =====================================================
     * 1. IDENTIDAD NUEVA + EMPRESA ALPHA
     * =====================================================
     */

    $userId = $service->createOrAttach(
        1,
        'Usuario',
        'Servicio',
        'Usuario Servicio',
        $temporaryEmail,
        '5555555555',
        'Test1234!'
    );

    checkCompanyUserService(
        'Se crea una identidad nueva',
        $userId > 0
    );

    $statement = $pdo->prepare("
        SELECT
            name,
            last_name,
            display_name,
            email,
            password_hash,
            phone,
            status
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $statement->execute([
        ':user_id' => $userId,
    ]);

    $user = $statement->fetch();

    checkCompanyUserService(
        'La identidad fue guardada correctamente',
        is_array($user)
        && $user['name'] === 'Usuario'
        && $user['last_name'] === 'Servicio'
        && $user['display_name'] === 'Usuario Servicio'
        && $user['email'] === $temporaryEmail
        && $user['phone'] === '5555555555'
        && $user['status'] === 'active'
    );

    checkCompanyUserService(
        'La contraseña fue almacenada como hash',
        is_array($user)
        && $user['password_hash'] !== 'Test1234!'
        && password_verify(
            'Test1234!',
            $user['password_hash']
        )
    );

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_companies
        WHERE user_id = :user_id
          AND company_id = 1
          AND status = 'active'
    ");

    $statement->execute([
        ':user_id' => $userId,
    ]);

    checkCompanyUserService(
        'Se crea la membresía en Empresa Alpha',
        (int) $statement->fetchColumn() === 1
    );

    /*
     * =====================================================
     * 2. MEMBRESÍA DUPLICADA
     * =====================================================
     */

    $duplicateDetected = false;

    try {
        $service->createOrAttach(
            1,
            'Nombre Alterado',
            'No Debe Guardarse',
            'Tampoco Debe Guardarse',
            $temporaryEmail,
            '0000000000',
            'OtraClave123!'
        );
    } catch (RuntimeException $exception) {
        $duplicateDetected =
            $exception->getMessage()
            === 'MEMBERSHIP_EXISTS';
    }

    checkCompanyUserService(
        'Detecta membresía empresarial duplicada',
        $duplicateDetected
    );

    /*
     * =====================================================
     * 3. MISMA IDENTIDAD EN EMPRESA BETA
     * =====================================================
     */

    $betaUserId = $service->createOrAttach(
        2,
        'Nombre Alterado',
        'No Debe Guardarse',
        'Tampoco Debe Guardarse',
        $temporaryEmail,
        '0000000000',
        'OtraClave123!'
    );

    checkCompanyUserService(
        'Beta reutiliza la misma identidad global',
        $betaUserId === $userId
    );

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE email = :email
    ");

    $statement->execute([
        ':email' => $temporaryEmail,
    ]);

    checkCompanyUserService(
        'No duplica la identidad global',
        (int) $statement->fetchColumn() === 1
    );

    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_companies
        WHERE user_id = :user_id
          AND status = 'active'
          AND company_id IN (1, 2)
    ");

    $statement->execute([
        ':user_id' => $userId,
    ]);

    checkCompanyUserService(
        'La identidad pertenece a Alpha y Beta',
        (int) $statement->fetchColumn() === 2
    );

    /*
     * =====================================================
     * 4. IDENTIDAD GLOBAL NO SE SOBRESCRIBE
     * =====================================================
     */

    $statement = $pdo->prepare("
        SELECT
            name,
            last_name,
            display_name,
            phone,
            password_hash
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $statement->execute([
        ':user_id' => $userId,
    ]);

    $userAfterBeta =
        $statement->fetch();

    checkCompanyUserService(
        'Beta no modifica los datos globales de identidad',
        is_array($userAfterBeta)
        && $userAfterBeta['name'] === 'Usuario'
        && $userAfterBeta['last_name'] === 'Servicio'
        && $userAfterBeta['display_name'] === 'Usuario Servicio'
        && $userAfterBeta['phone'] === '5555555555'
    );

    checkCompanyUserService(
        'Beta no modifica la contraseña global',
        is_array($userAfterBeta)
        && password_verify(
            'Test1234!',
            $userAfterBeta['password_hash']
        )
        && !password_verify(
            'OtraClave123!',
            $userAfterBeta['password_hash']
        )
    );
} catch (Throwable $exception) {
    $failed++;

    echo '[ERROR] '
        . $exception->getMessage()
        . PHP_EOL;
} finally {
    /*
     * Este servicio controla sus propias transacciones,
     * por lo que limpiamos explícitamente los fixtures.
     *
     * Primero eliminamos las membresías por la FK
     * y después la identidad temporal.
     */

    if ($userId !== null) {
        try {
            $pdo->beginTransaction();

            $statement = $pdo->prepare("
                DELETE FROM user_companies
                WHERE user_id = :user_id
            ");

            $statement->execute([
                ':user_id' => $userId,
            ]);

            $statement = $pdo->prepare("
                DELETE FROM users
                WHERE id = :user_id
            ");

            $statement->execute([
                ':user_id' => $userId,
            ]);

            $pdo->commit();
        } catch (Throwable $cleanupException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $failed++;

            echo '[ERROR CLEANUP] '
                . $cleanupException->getMessage()
                . PHP_EOL;
        }
    }
}

echo str_repeat('-', 78) . PHP_EOL;
echo "Correctas: {$correct}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);