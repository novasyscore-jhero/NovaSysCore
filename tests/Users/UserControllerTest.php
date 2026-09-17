<?php

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Http\Controllers\Users\UserController;
use NovaSysCore\Context\CompanyContext;
use NovaSysCore\Context\CompanyContextStore;
use NovaSysCore\Database;

echo PHP_EOL;
echo "NovaSysCore - User Controller Test" . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;

$correct = 0;
$failed = 0;

function checkUserController(
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

function executeUserShow(
    UserController $controller,
    string $id
): array {
    http_response_code(200);

    ob_start();

    $controller->show($id);

    return [
        'status' => http_response_code(),
        'output' => trim(
            ob_get_clean()
        ),
    ];
}

$pdo = Database::connection();

$startedTransaction = false;

try {
    /*
     * =====================================================
     * DATOS TEMPORALES
     * =====================================================
     *
     * Todo lo creado dentro de esta prueba será eliminado
     * mediante ROLLBACK al finalizar.
     */

    $pdo->beginTransaction();

    $startedTransaction = true;

    $temporaryEmail =
        'user-controller-beta-'
        . bin2hex(random_bytes(8))
        . '@novasyscore.local';

    $statement = $pdo->prepare("
        INSERT INTO users (
            name,
            email,
            password_hash,
            status
        )
        VALUES (
            :name,
            :email,
            :password_hash,
            'active'
        )
    ");

    $statement->execute([
        'name' => 'Usuario Beta Temporal',
        'email' => $temporaryEmail,
        'password_hash' => password_hash(
            'Test1234!',
            PASSWORD_DEFAULT
        ),
    ]);

    $betaUserId =
        (int) $pdo->lastInsertId();

    checkUserController(
        'Usuario temporal Beta fue creado',
        $betaUserId > 0
    );

    /*
     * El usuario pertenece EXCLUSIVAMENTE
     * a Empresa Beta.
     */
    $statement = $pdo->prepare("
        INSERT INTO user_companies (
            user_id,
            company_id,
            status
        )
        VALUES (
            :user_id,
            :company_id,
            'active'
        )
    ");

    $statement->execute([
        'user_id' => $betaUserId,
        'company_id' => 2,
    ]);

    $controller =
        new UserController();

    /*
     * =====================================================
     * 1. ID inválido
     * =====================================================
     */

    CompanyContextStore::clear();

    $result = executeUserShow(
        $controller,
        'abc'
    );

    checkUserController(
        'ID no numerico devuelve 404',
        $result['status'] === 404
    );

    checkUserController(
        'ID no numerico no revela informacion',
        $result['output']
            === 'Usuario no encontrado.'
    );

    /*
     * =====================================================
     * 2. ID inexistente
     * =====================================================
     */

    $alphaContext =
        new CompanyContext(
            1,
            1,
            null
        );

    CompanyContextStore::set(
        $alphaContext
    );

    $result = executeUserShow(
        $controller,
        '999999999'
    );

    checkUserController(
        'Usuario inexistente devuelve 404',
        $result['status'] === 404
    );

    /*
     * =====================================================
     * 3. AISLAMIENTO MULTIEMPRESA
     * =====================================================
     *
     * Estamos dentro de Empresa Alpha.
     * Intentamos consultar directamente el ID
     * de un usuario que pertenece solo a Beta.
     */

    $result = executeUserShow(
        $controller,
        (string) $betaUserId
    );

    checkUserController(
        'Empresa Alpha no puede ver usuario exclusivo de Beta',
        $result['status'] === 404
    );

    checkUserController(
        'Acceso cruzado no revela existencia del usuario',
        $result['output']
            === 'Usuario no encontrado.'
    );

    /*
     * =====================================================
     * 4. MISMO USUARIO DESDE EMPRESA BETA
     * =====================================================
     *
     * Cambiamos únicamente el contexto empresarial.
     * Ahora el mismo ID sí debe ser visible.
     */

    $betaContext =
        new CompanyContext(
            1,
            2,
            null
        );

    CompanyContextStore::set(
        $betaContext
    );

    $result = executeUserShow(
        $controller,
        (string) $betaUserId
    );

    checkUserController(
        'Empresa Beta puede ver su propio usuario',
        $result['status'] === 200
    );

    checkUserController(
        'Empresa Beta recibe el usuario correcto',
        $result['output']
            === 'Usuario Beta Temporal'
    );

    /*
     * =====================================================
     * 5. CONTEXTO AUSENTE
     * =====================================================
     */

    CompanyContextStore::clear();

    $result = executeUserShow(
        $controller,
        (string) $betaUserId
    );

    checkUserController(
        'Sin contexto empresarial devuelve 403',
        $result['status'] === 403
    );

    checkUserController(
        'Sin contexto empresarial no muestra usuario',
        $result['output']
            === 'No existe un contexto empresarial válido.'
    );
} catch (Throwable $exception) {
    $failed++;

    echo '[ERROR] '
        . $exception->getMessage()
        . PHP_EOL;
} finally {
    /*
     * Limpiamos siempre el contexto request-local.
     */
    CompanyContextStore::clear();

    /*
     * Ningún dato de esta prueba debe permanecer
     * en la base de datos.
     */
    if (
        $startedTransaction
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }
}

echo str_repeat('-', 78) . PHP_EOL;
echo "Correctas: {$correct}" . PHP_EOL;
echo "Fallidas:  {$failed}" . PHP_EOL;