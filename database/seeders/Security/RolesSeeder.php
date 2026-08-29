<?php

namespace NovaSysCore\Database\Seeders\Security;

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $pdo = Database::connection();

        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-administrator',
                'description' => 'Rol global con acceso total al sistema NovaSysCore.',
            ],
            [
                'name' => 'System Administrator',
                'slug' => 'system-administrator',
                'description' => 'Rol global para administración operativa del sistema.',
            ],
        ];

        $findStatement = $pdo->prepare("
            SELECT id
            FROM roles
            WHERE company_id IS NULL
              AND slug = :slug
            LIMIT 1
        ");

        $insertStatement = $pdo->prepare("
            INSERT INTO roles (
                company_id,
                name,
                slug,
                description,
                is_system,
                status
            ) VALUES (
                NULL,
                :name,
                :slug,
                :description,
                TRUE,
                'active'
            )
        ");

        $updateStatement = $pdo->prepare("
            UPDATE roles
            SET
                name = :name,
                description = :description,
                is_system = TRUE,
                status = 'active'
            WHERE id = :id
        ");

        foreach ($roles as $role) {
            $findStatement->execute([
                'slug' => $role['slug'],
            ]);

            $existingRole = $findStatement->fetch();

            if ($existingRole) {
                $updateStatement->execute([
                    'id' => $existingRole['id'],
                    'name' => $role['name'],
                    'description' => $role['description'],
                ]);

                continue;
            }

            $insertStatement->execute([
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
            ]);
        }

        echo '[OK] Roles base registrados: '
            . count($roles)
            . PHP_EOL;
    }
}