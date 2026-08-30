<?php

namespace NovaSysCore\Security;

use NovaSysCore\Database;
use PDO;
use RuntimeException;

class SystemRoleAssignmentService
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function assignSystemRole(
        int $actorUserId,
        int $targetUserId,
        int $roleId
    ): void {
        $this->assertActiveUser($actorUserId);
        $this->assertActiveUser($targetUserId);

        $role = $this->getSystemRole($roleId);

        if ($role === null) {
            throw new RuntimeException(
                'El rol indicado no es un rol global de sistema válido.'
            );
        }

        /*
         * Por ahora, únicamente un Super Administrator puede
         * asignar roles globales.
         */
        if (!$this->isSuperAdministrator($actorUserId)) {
            throw new RuntimeException(
                'El usuario actual no tiene autorización para asignar roles globales.'
            );
        }

        /*
         * Evitamos duplicados.
         */
        $statement = $this->pdo->prepare("
            SELECT id
            FROM user_system_roles
            WHERE user_id = :user_id
              AND role_id = :role_id
            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $targetUserId,
            'role_id' => $roleId,
        ]);

        if ($statement->fetchColumn() !== false) {
            return;
        }

        $statement = $this->pdo->prepare("
            INSERT INTO user_system_roles (
                user_id,
                role_id,
                created_at,
                updated_at
            )
            VALUES (
                :user_id,
                :role_id,
                NOW(),
                NOW()
            )
        ");

        $statement->execute([
            'user_id' => $targetUserId,
            'role_id' => $roleId,
        ]);
    }

    public function removeSystemRole(
        int $actorUserId,
        int $targetUserId,
        int $roleId
    ): void {
        $this->assertActiveUser($actorUserId);

        $role = $this->getSystemRole($roleId);

        if ($role === null) {
            throw new RuntimeException(
                'El rol indicado no es un rol global de sistema válido.'
            );
        }

        if (!$this->isSuperAdministrator($actorUserId)) {
            throw new RuntimeException(
                'El usuario actual no tiene autorización para retirar roles globales.'
            );
        }

        /*
         * Protección básica:
         * un Super Administrator no puede retirarse a sí mismo
         * su propio rol desde este servicio.
         *
         * Más adelante podemos sustituir esto por una política
         * más sofisticada de último Super Administrator activo.
         */
        if (
            $actorUserId === $targetUserId
            && $role['slug'] === 'super-administrator'
        ) {
            throw new RuntimeException(
                'Un Super Administrator no puede retirar su propio rol global.'
            );
        }

        $statement = $this->pdo->prepare("
            DELETE FROM user_system_roles
            WHERE user_id = :user_id
              AND role_id = :role_id
        ");

        $statement->execute([
            'user_id' => $targetUserId,
            'role_id' => $roleId,
        ]);
    }

    private function assertActiveUser(int $userId): void
    {
        $statement = $this->pdo->prepare("
            SELECT id
            FROM users
            WHERE id = :user_id
              AND status = 'active'
            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);

        if ($statement->fetchColumn() === false) {
            throw new RuntimeException(
                'El usuario no existe o se encuentra inactivo.'
            );
        }
    }

    private function getSystemRole(int $roleId): ?array
    {
        $statement = $this->pdo->prepare("
            SELECT
                id,
                slug,
                name,
                company_id,
                is_system,
                status
            FROM roles
            WHERE id = :role_id
              AND company_id IS NULL
              AND is_system = TRUE
              AND status = 'active'
            LIMIT 1
        ");

        $statement->execute([
            'role_id' => $roleId,
        ]);

        $role = $statement->fetch(PDO::FETCH_ASSOC);

        return $role ?: null;
    }

    private function isSuperAdministrator(int $userId): bool
    {
        $statement = $this->pdo->prepare("
            SELECT 1
            FROM user_system_roles usr

            INNER JOIN roles r
                ON r.id = usr.role_id

            WHERE usr.user_id = :user_id
              AND r.company_id IS NULL
              AND r.slug = 'super-administrator'
              AND r.is_system = TRUE
              AND r.status = 'active'

            LIMIT 1
        ");

        $statement->execute([
            'user_id' => $userId,
        ]);

        return $statement->fetchColumn() !== false;
    }
}