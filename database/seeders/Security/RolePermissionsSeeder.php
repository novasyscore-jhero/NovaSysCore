<?php

namespace NovaSysCore\Database\Seeders\Security;

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Seeder;

class RolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $pdo = Database::connection();

        $roleStatement = $pdo->prepare("
            SELECT id
            FROM roles
            WHERE company_id IS NULL
              AND slug = :slug
            LIMIT 1
        ");

        $roleStatement->execute([
            'slug' => 'system-administrator',
        ]);

        $role = $roleStatement->fetch();

        if (!$role) {
            throw new \RuntimeException(
                'No se encontró el rol global system-administrator.'
            );
        }

        $permissionStatement = $pdo->prepare("
            SELECT id
            FROM permissions
            WHERE status = 'active'
              AND is_system = TRUE
        ");

        $permissionStatement->execute();

        $permissions = $permissionStatement->fetchAll();

        if (!$permissions) {
            throw new \RuntimeException(
                'No existen permisos activos del sistema para asignar.'
            );
        }

        $insertStatement = $pdo->prepare("
            INSERT INTO role_permissions (
                role_id,
                permission_id
            ) VALUES (
                :role_id,
                :permission_id
            )
            ON DUPLICATE KEY UPDATE
                role_id = VALUES(role_id)
        ");

        foreach ($permissions as $permission) {
            $insertStatement->execute([
                'role_id' => $role['id'],
                'permission_id' => $permission['id'],
            ]);
        }

        echo '[OK] Permisos asignados a System Administrator: '
            . count($permissions)
            . PHP_EOL;
    }
}