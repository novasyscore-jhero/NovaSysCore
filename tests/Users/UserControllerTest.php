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
     * 1. ID INVÁLIDO
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
     * 2. ID INEXISTENTE
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
        'Empresa Beta muestra el nombre del usuario',
        str_contains(
            $result['output'],
            'Usuario Beta Temporal'
        )
    );

    checkUserController(
        'Empresa Beta muestra el correo correcto',
        str_contains(
            $result['output'],
            $temporaryEmail
        )
    );

    checkUserController(
        'Vista del usuario no expone password_hash',
        !str_contains(
            $result['output'],
            'password_hash'
        )
    );

    /*
    * =====================================================
    * 5. INFORMACIÓN DE MEMBRESÍA EMPRESARIAL
    * =====================================================
    */

    checkUserController(
        'Vista distingue el estado global del usuario',
        str_contains(
            $result['output'],
            'Estado global'
        )
    );

    checkUserController(
        'Empresa Beta muestra su propia membresía',
        str_contains(
            $result['output'],
            'Empresa Beta'
        )
    );

    checkUserController(
        'Empresa Beta muestra membresía activa',
        str_contains(
            $result['output'],
            'Estado de membresía'
        )
        && str_contains(
            $result['output'],
            'Activa'
        )
    );

    checkUserController(
        'Vista muestra fecha de incorporación empresarial',
        str_contains(
            $result['output'],
            'Miembro desde'
        )
    );

    /*
    * =====================================================
    * 6. MISMA IDENTIDAD EN ALPHA Y BETA
    * =====================================================
    *
    * Hasta este punto el usuario pertenecía exclusivamente
    * a Beta. Ahora agregamos una membresía Alpha para
    * comprobar que la vista depende del contexto actual.
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
        'company_id' => 1,
    ]);

    CompanyContextStore::set(
        $alphaContext
    );

    $alphaResult = executeUserShow(
        $controller,
        (string) $betaUserId
    );

    checkUserController(
        'Usuario compartido puede verse desde Alpha',
        $alphaResult['status'] === 200
    );

    checkUserController(
        'Contexto Alpha muestra Empresa Alpha',
        str_contains(
            $alphaResult['output'],
            'Empresa Alpha'
        )
    );

    checkUserController(
        'Contexto Alpha no muestra Empresa Beta',
        !str_contains(
            $alphaResult['output'],
            'Empresa Beta'
        )
    );

    CompanyContextStore::set(
        $betaContext
    );

    $betaResult = executeUserShow(
        $controller,
        (string) $betaUserId
    );

    checkUserController(
        'Usuario compartido puede verse desde Beta',
        $betaResult['status'] === 200
    );

    checkUserController(
        'Contexto Beta muestra Empresa Beta',
        str_contains(
            $betaResult['output'],
            'Empresa Beta'
        )
    );

    checkUserController(
        'Contexto Beta no muestra Empresa Alpha',
        !str_contains(
            $betaResult['output'],
            'Empresa Alpha'
        )
    );

    /*
    * =====================================================
    * 7. AISLAMIENTO DE ROLES EMPRESARIALES
    * =====================================================
    */

    $alphaRoleName =
        'Rol Alpha Temporal '
        . bin2hex(random_bytes(4));

    $betaRoleName =
        'Rol Beta Temporal '
        . bin2hex(random_bytes(4));

    $alphaRoleSlug =
        'rol-alpha-temporal-'
        . bin2hex(random_bytes(4));

    $betaRoleSlug =
        'rol-beta-temporal-'
        . bin2hex(random_bytes(4));

    $statement = $pdo->prepare("
        INSERT INTO roles (
            company_id,
            name,
            slug,
            status
        )
        VALUES (
            :company_id,
            :name,
            :slug,
            'active'
        )
    ");

    $statement->execute([
        'company_id' => 1,
        'name' => $alphaRoleName,
        'slug' => $alphaRoleSlug,
    ]);

    $alphaRoleId =
        (int) $pdo->lastInsertId();

    $statement->execute([
        'company_id' => 2,
        'name' => $betaRoleName,
        'slug' => $betaRoleSlug,
    ]);

    $betaRoleId =
        (int) $pdo->lastInsertId();

    /*
    * Obtenemos las dos membresías del mismo usuario.
    */
    $statement = $pdo->prepare("
        SELECT
            id,
            company_id
        FROM user_companies
        WHERE user_id = :user_id
        AND company_id IN (1, 2)
        AND status = 'active'
    ");

    $statement->execute([
        'user_id' => $betaUserId,
    ]);

    $membershipIds = [];

    foreach ($statement->fetchAll() as $membership) {
        $membershipIds[
            (int) $membership['company_id']
        ] = (int) $membership['id'];
    }

    checkUserController(
        'Usuario temporal tiene membresías Alpha y Beta',
        isset(
            $membershipIds[1],
            $membershipIds[2]
        )
    );

    /*
    * Asignamos un rol diferente en cada empresa.
    */
    $statement = $pdo->prepare("
        INSERT INTO user_company_roles (
            user_company_id,
            role_id,
            branch_scope
        )
        VALUES (
            :user_company_id,
            :role_id,
            'all'
        )
    ");

    $statement->execute([
        'user_company_id' => $membershipIds[1],
        'role_id' => $alphaRoleId,
    ]);

    $statement->execute([
        'user_company_id' => $membershipIds[2],
        'role_id' => $betaRoleId,
    ]);

    /*
    * Contexto Alpha.
    */
    CompanyContextStore::set(
        $alphaContext
    );

    $alphaRoleResult = executeUserShow(
        $controller,
        (string) $betaUserId
    );

    checkUserController(
        'Contexto Alpha muestra su rol empresarial',
        str_contains(
            $alphaRoleResult['output'],
            $alphaRoleName
        )
    );

    checkUserController(
        'Contexto Alpha no filtra rol de Beta',
        !str_contains(
            $alphaRoleResult['output'],
            $betaRoleName
        )
    );

    /*
    * Contexto Beta.
    */
    CompanyContextStore::set(
        $betaContext
    );

    $betaRoleResult = executeUserShow(
        $controller,
        (string) $betaUserId
    );

    checkUserController(
        'Contexto Beta muestra su rol empresarial',
        str_contains(
            $betaRoleResult['output'],
            $betaRoleName
        )
    );

    checkUserController(
        'Contexto Beta no filtra rol de Alpha',
        !str_contains(
            $betaRoleResult['output'],
            $alphaRoleName
        )
    );

    /*
     * =====================================================
     * 8. CONTEXTO AUSENTE
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
    CompanyContextStore::clear();

    /*
     * Ningún dato temporal de esta prueba
     * debe permanecer en la base de datos.
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