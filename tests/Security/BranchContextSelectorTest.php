<?php

require_once __DIR__ . '/../../bootstrap/app.php';

use NovaSysCore\Context\BranchContextSelector;
use NovaSysCore\Database;

class BranchContextSelectorTest
{
    private \PDO $pdo;
    private BranchContextSelector $selector;

    private int $alphaId;
    private int $betaId;

    private int $centroId;
    private int $norteId;
    private int $betaBranchId;

    private int $normalUserId;
    private int $superAdminUserId;

    private int $normalUserCompanyId;

    private int $selectedRoleId;
    private int $allRoleId;

    private int $correct = 0;
    private int $failed = 0;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->selector = new BranchContextSelector();
    }

    public function run(): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->prepareScenario();

            $this->testInvalidUser();
            $this->testInvalidCompany();
            $this->testSelectedScope();
            $this->testAllScope();
            $this->testUnionSelectedRoles();
            $this->testInactiveBranchExcluded();
            $this->testWrongCompanyExcluded();
            $this->testSuperAdministrator();
            $this->testInactiveUser();
            $this->testInactiveCompany();

            $this->printSummary();
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        }
    }

    private function prepareScenario(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $this->alphaId = $this->createCompany(
            'Branch Alpha ' . $suffix,
            'branch-alpha-' . $suffix
        );

        $this->betaId = $this->createCompany(
            'Branch Beta ' . $suffix,
            'branch-beta-' . $suffix
        );

        $this->centroId = $this->createBranch(
            $this->alphaId,
            'Sucursal Centro',
            'CENTRO-' . $suffix,
            'centro-' . $suffix
        );

        $this->norteId = $this->createBranch(
            $this->alphaId,
            'Sucursal Norte',
            'NORTE-' . $suffix,
            'norte-' . $suffix
        );

        $this->betaBranchId = $this->createBranch(
            $this->betaId,
            'Sucursal Beta',
            'BETA-' . $suffix,
            'beta-' . $suffix
        );

        $this->normalUserId = $this->createUser(
            'branch.normal.' . $suffix . '@test.local'
        );

        $this->superAdminUserId = $this->createUser(
            'branch.super.' . $suffix . '@test.local'
        );

        $this->normalUserCompanyId =
            $this->createMembership(
                $this->normalUserId,
                $this->alphaId
            );

        $this->selectedRoleId = $this->createCompanyRole(
            $this->alphaId,
            'Selected Role ' . $suffix,
            'selected-role-' . $suffix
        );

        $this->allRoleId = $this->createCompanyRole(
            $this->alphaId,
            'All Role ' . $suffix,
            'all-role-' . $suffix
        );

        $superRoleId = $this->findSuperAdministratorRole();

        $statement = $this->pdo->prepare("
            INSERT INTO user_system_roles (
                user_id,
                role_id
            )
            VALUES (
                :user_id,
                :role_id
            )
        ");

        $statement->execute([
            'user_id' => $this->superAdminUserId,
            'role_id' => $superRoleId,
        ]);
    }

    private function testInvalidUser(): void
    {
        $branches = $this->selector->availableBranches(
            999999999,
            $this->alphaId
        );

        $this->assertCount(
            0,
            $branches,
            'Usuario inexistente'
        );
    }

    private function testInvalidCompany(): void
    {
        $branches = $this->selector->availableBranches(
            $this->normalUserId,
            999999999
        );

        $this->assertCount(
            0,
            $branches,
            'Empresa inexistente'
        );
    }

    private function testSelectedScope(): void
    {
        $userCompanyRoleId =
            $this->assignCompanyRole(
                $this->normalUserCompanyId,
                $this->selectedRoleId,
                'selected'
            );

        $this->assignBranch(
            $userCompanyRoleId,
            $this->centroId
        );

        $branches = $this->selector->availableBranches(
            $this->normalUserId,
            $this->alphaId
        );

        $this->assertBranchIds(
            [$this->centroId],
            $branches,
            'Selected devuelve solo Centro'
        );

        $this->deleteCompanyRoleAssignment(
            $userCompanyRoleId
        );
    }

    private function testAllScope(): void
    {
        $userCompanyRoleId =
            $this->assignCompanyRole(
                $this->normalUserCompanyId,
                $this->allRoleId,
                'all'
            );

        $branches = $this->selector->availableBranches(
            $this->normalUserId,
            $this->alphaId
        );

        $this->assertBranchIds(
            [
                $this->centroId,
                $this->norteId,
            ],
            $branches,
            'All devuelve todas las sucursales Alpha'
        );

        $this->deleteCompanyRoleAssignment(
            $userCompanyRoleId
        );
    }

    private function testUnionSelectedRoles(): void
    {
        $suffix = bin2hex(random_bytes(3));

        $secondRoleId = $this->createCompanyRole(
            $this->alphaId,
            'Second Selected ' . $suffix,
            'second-selected-' . $suffix
        );

        $roleOne =
            $this->assignCompanyRole(
                $this->normalUserCompanyId,
                $this->selectedRoleId,
                'selected'
            );

        $roleTwo =
            $this->assignCompanyRole(
                $this->normalUserCompanyId,
                $secondRoleId,
                'selected'
            );

        $this->assignBranch(
            $roleOne,
            $this->centroId
        );

        $this->assignBranch(
            $roleTwo,
            $this->norteId
        );

        $branches = $this->selector->availableBranches(
            $this->normalUserId,
            $this->alphaId
        );

        $this->assertBranchIds(
            [
                $this->centroId,
                $this->norteId,
            ],
            $branches,
            'Unión de múltiples roles selected'
        );

        $this->deleteCompanyRoleAssignment($roleOne);
        $this->deleteCompanyRoleAssignment($roleTwo);
    }

    private function testInactiveBranchExcluded(): void
    {
        $userCompanyRoleId =
            $this->assignCompanyRole(
                $this->normalUserCompanyId,
                $this->selectedRoleId,
                'selected'
            );

        $this->assignBranch(
            $userCompanyRoleId,
            $this->centroId
        );

        $this->pdo->prepare("
            UPDATE branches
            SET status = 'inactive'
            WHERE id = :id
        ")->execute([
            'id' => $this->centroId,
        ]);

        $branches = $this->selector->availableBranches(
            $this->normalUserId,
            $this->alphaId
        );

        $this->assertCount(
            0,
            $branches,
            'Sucursal inactiva queda excluida'
        );

        $this->pdo->prepare("
            UPDATE branches
            SET status = 'active'
            WHERE id = :id
        ")->execute([
            'id' => $this->centroId,
        ]);

        $this->deleteCompanyRoleAssignment(
            $userCompanyRoleId
        );
    }

    private function testWrongCompanyExcluded(): void
    {
        $userCompanyRoleId =
            $this->assignCompanyRole(
                $this->normalUserCompanyId,
                $this->selectedRoleId,
                'selected'
            );

        $this->assignBranch(
            $userCompanyRoleId,
            $this->centroId
        );

        $branches = $this->selector->availableBranches(
            $this->normalUserId,
            $this->betaId
        );

        $this->assertCount(
            0,
            $branches,
            'No cruza sucursales entre empresas'
        );

        $this->deleteCompanyRoleAssignment(
            $userCompanyRoleId
        );
    }

    private function testSuperAdministrator(): void
    {
        $branches = $this->selector->availableBranches(
            $this->superAdminUserId,
            $this->alphaId
        );

        $this->assertBranchIds(
            [
                $this->centroId,
                $this->norteId,
            ],
            $branches,
            'Super Admin ve todas las sucursales activas'
        );
    }

    private function testInactiveUser(): void
    {
        $this->pdo->prepare("
            UPDATE users
            SET status = 'inactive'
            WHERE id = :id
        ")->execute([
            'id' => $this->normalUserId,
        ]);

        $branches = $this->selector->availableBranches(
            $this->normalUserId,
            $this->alphaId
        );

        $this->assertCount(
            0,
            $branches,
            'Usuario inactivo no obtiene sucursales'
        );

        $this->pdo->prepare("
            UPDATE users
            SET status = 'active'
            WHERE id = :id
        ")->execute([
            'id' => $this->normalUserId,
        ]);
    }

    private function testInactiveCompany(): void
    {
        $this->pdo->prepare("
            UPDATE companies
            SET status = 'inactive'
            WHERE id = :id
        ")->execute([
            'id' => $this->alphaId,
        ]);

        $branches = $this->selector->availableBranches(
            $this->superAdminUserId,
            $this->alphaId
        );

        $this->assertCount(
            0,
            $branches,
            'Empresa inactiva no devuelve sucursales'
        );

        $this->pdo->prepare("
            UPDATE companies
            SET status = 'active'
            WHERE id = :id
        ")->execute([
            'id' => $this->alphaId,
        ]);
    }

    private function createCompany(
    string $name,
    string $slug
    ): int {
        $statement = $this->pdo->prepare("
            INSERT INTO companies (
                name,
                code,
                slug,
                tax_id,
                status
            )
            VALUES (
                :name,
                :code,
                :slug,
                :tax_id,
                'active'
            )
        ");

        $statement->execute([
            'name' => $name,
            'code' => strtoupper(
                substr(md5($slug), 0, 12)
            ),
            'slug' => $slug,
            'tax_id' => strtoupper(
                substr(md5('tax-' . $slug), 0, 12)
            ),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createBranch(
        int $companyId,
        string $name,
        string $code,
        string $slug
    ): int {
        $statement = $this->pdo->prepare("
            INSERT INTO branches (
                company_id,
                name,
                code,
                slug,
                status
            )
            VALUES (
                :company_id,
                :name,
                :code,
                :slug,
                'active'
            )
        ");

        $statement->execute([
            'company_id' => $companyId,
            'name' => $name,
            'code' => $code,
            'slug' => $slug,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createUser(
        string $email
    ): int {
        $statement = $this->pdo->prepare("
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
            'name' => 'Branch Context Test',
            'email' => $email,
            'password_hash' => password_hash(
                'Test1234!',
                PASSWORD_DEFAULT
            ),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createMembership(
        int $userId,
        int $companyId
    ): int {
        $statement = $this->pdo->prepare("
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
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createCompanyRole(
        int $companyId,
        string $name,
        string $slug
    ): int {
        $statement = $this->pdo->prepare("
            INSERT INTO roles (
                company_id,
                name,
                slug,
                is_system,
                status
            )
            VALUES (
                :company_id,
                :name,
                :slug,
                FALSE,
                'active'
            )
        ");

        $statement->execute([
            'company_id' => $companyId,
            'name' => $name,
            'slug' => $slug,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assignCompanyRole(
        int $userCompanyId,
        int $roleId,
        string $branchScope
    ): int {
        $statement = $this->pdo->prepare("
            INSERT INTO user_company_roles (
                user_company_id,
                role_id,
                branch_scope
            )
            VALUES (
                :user_company_id,
                :role_id,
                :branch_scope
            )
        ");

        $statement->execute([
            'user_company_id' => $userCompanyId,
            'role_id' => $roleId,
            'branch_scope' => $branchScope,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assignBranch(
        int $userCompanyRoleId,
        int $branchId
    ): void {
        $statement = $this->pdo->prepare("
            INSERT INTO user_company_branches (
                user_company_role_id,
                branch_id
            )
            VALUES (
                :user_company_role_id,
                :branch_id
            )
        ");

        $statement->execute([
            'user_company_role_id' =>
                $userCompanyRoleId,
            'branch_id' =>
                $branchId,
        ]);
    }

    private function deleteCompanyRoleAssignment(
        int $userCompanyRoleId
    ): void {
        $this->pdo->prepare("
            DELETE FROM user_company_branches
            WHERE user_company_role_id = :id
        ")->execute([
            'id' => $userCompanyRoleId,
        ]);

        $this->pdo->prepare("
            DELETE FROM user_company_roles
            WHERE id = :id
        ")->execute([
            'id' => $userCompanyRoleId,
        ]);
    }

    private function findSuperAdministratorRole(): int
    {
        $statement = $this->pdo->query("
            SELECT id
            FROM roles
            WHERE company_id IS NULL
              AND slug = 'super-administrator'
              AND is_system = TRUE
              AND status = 'active'
            LIMIT 1
        ");

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'No existe el rol super-administrator.'
            );
        }

        return (int) $id;
    }

    private function assertCount(
        int $expected,
        array $branches,
        string $label
    ): void {
        $obtained = count($branches);

        $this->assert(
            $expected === $obtained,
            $label,
            "esperado={$expected} obtenido={$obtained}"
        );
    }

    private function assertBranchIds(
        array $expectedIds,
        array $branches,
        string $label
    ): void {
        $obtainedIds = array_map(
            static fn(array $branch): int =>
                (int) $branch['id'],
            $branches
        );

        sort($expectedIds);
        sort($obtainedIds);

        $this->assert(
            $expectedIds === $obtainedIds,
            $label,
            'esperado=[' . implode(',', $expectedIds)
                . '] obtenido=['
                . implode(',', $obtainedIds)
                . ']'
        );
    }

    private function assert(
        bool $condition,
        string $label,
        string $detail
    ): void {
        if ($condition) {
            $this->correct++;

            echo "[OK] {$label}"
                . str_repeat(
                    ' ',
                    max(1, 48 - strlen($label))
                )
                . $detail
                . PHP_EOL;

            return;
        }

        $this->failed++;

        echo "[FAIL] {$label}"
            . str_repeat(
                ' ',
                max(1, 46 - strlen($label))
            )
            . $detail
            . PHP_EOL;
    }

    private function printSummary(): void
    {
        echo PHP_EOL;
        echo str_repeat('-', 72) . PHP_EOL;
        echo "Correctas: {$this->correct}" . PHP_EOL;
        echo "Fallidas:  {$this->failed}" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "NovaSysCore - Branch Context Selector Test";
echo PHP_EOL;
echo str_repeat('=', 72);
echo PHP_EOL;

$test = new BranchContextSelectorTest();
$test->run();