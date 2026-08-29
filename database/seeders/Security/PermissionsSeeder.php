<?php

namespace NovaSysCore\Database\Seeders\Security;

use NovaSysCore\Database;
use NovaSysCore\Database\Seeders\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $pdo = Database::connection();

        $permissions = [
            [
                'name' => 'Ver empresas',
                'slug' => 'companies.view',
                'description' => 'Permite consultar empresas.',
                'module' => 'companies',
                'action' => 'view',
            ],
            [
                'name' => 'Crear empresas',
                'slug' => 'companies.create',
                'description' => 'Permite crear nuevas empresas.',
                'module' => 'companies',
                'action' => 'create',
            ],
            [
                'name' => 'Actualizar empresas',
                'slug' => 'companies.update',
                'description' => 'Permite modificar empresas existentes.',
                'module' => 'companies',
                'action' => 'update',
            ],

            [
                'name' => 'Ver sucursales',
                'slug' => 'branches.view',
                'description' => 'Permite consultar sucursales.',
                'module' => 'branches',
                'action' => 'view',
            ],
            [
                'name' => 'Crear sucursales',
                'slug' => 'branches.create',
                'description' => 'Permite crear nuevas sucursales.',
                'module' => 'branches',
                'action' => 'create',
            ],
            [
                'name' => 'Actualizar sucursales',
                'slug' => 'branches.update',
                'description' => 'Permite modificar sucursales existentes.',
                'module' => 'branches',
                'action' => 'update',
            ],

            [
                'name' => 'Ver usuarios',
                'slug' => 'users.view',
                'description' => 'Permite consultar usuarios.',
                'module' => 'users',
                'action' => 'view',
            ],
            [
                'name' => 'Crear usuarios',
                'slug' => 'users.create',
                'description' => 'Permite crear usuarios.',
                'module' => 'users',
                'action' => 'create',
            ],
            [
                'name' => 'Actualizar usuarios',
                'slug' => 'users.update',
                'description' => 'Permite modificar usuarios existentes.',
                'module' => 'users',
                'action' => 'update',
            ],
            [
                'name' => 'Desactivar usuarios',
                'slug' => 'users.disable',
                'description' => 'Permite desactivar usuarios.',
                'module' => 'users',
                'action' => 'disable',
            ],

            [
                'name' => 'Ver roles',
                'slug' => 'roles.view',
                'description' => 'Permite consultar roles.',
                'module' => 'roles',
                'action' => 'view',
            ],
            [
                'name' => 'Crear roles',
                'slug' => 'roles.create',
                'description' => 'Permite crear roles.',
                'module' => 'roles',
                'action' => 'create',
            ],
            [
                'name' => 'Actualizar roles',
                'slug' => 'roles.update',
                'description' => 'Permite modificar roles existentes.',
                'module' => 'roles',
                'action' => 'update',
            ],
            [
                'name' => 'Asignar roles',
                'slug' => 'roles.assign',
                'description' => 'Permite asignar roles a usuarios.',
                'module' => 'roles',
                'action' => 'assign',
            ],

            [
                'name' => 'Ver permisos',
                'slug' => 'permissions.view',
                'description' => 'Permite consultar el catálogo de permisos.',
                'module' => 'permissions',
                'action' => 'view',
            ],

            [
                'name' => 'Ver geografía',
                'slug' => 'geography.view',
                'description' => 'Permite consultar el catálogo geográfico.',
                'module' => 'geography',
                'action' => 'view',
            ],
        ];

        $statement = $pdo->prepare("
            INSERT INTO permissions (
                name,
                slug,
                description,
                module,
                action,
                is_system,
                status
            ) VALUES (
                :name,
                :slug,
                :description,
                :module,
                :action,
                TRUE,
                'active'
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                module = VALUES(module),
                action = VALUES(action),
                is_system = TRUE,
                status = 'active'
        ");

        foreach ($permissions as $permission) {
            $statement->execute($permission);
        }

        echo '[OK] Permisos base registrados: '
            . count($permissions)
            . PHP_EOL;
    }
}