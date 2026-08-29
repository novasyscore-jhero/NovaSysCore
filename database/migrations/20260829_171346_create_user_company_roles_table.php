<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateUserCompanyRolesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE user_company_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                user_company_id BIGINT UNSIGNED NOT NULL,

                role_id BIGINT UNSIGNED NOT NULL,

                branch_scope VARCHAR(20) NOT NULL DEFAULT 'all',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_user_company_roles_membership_role
                    (user_company_id, role_id),

                KEY idx_user_company_roles_membership
                    (user_company_id),

                KEY idx_user_company_roles_role
                    (role_id),

                KEY idx_user_company_roles_scope
                    (branch_scope),

                CONSTRAINT fk_user_company_roles_membership
                    FOREIGN KEY (user_company_id)
                    REFERENCES user_companies(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_user_company_roles_role
                    FOREIGN KEY (role_id)
                    REFERENCES roles(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS user_company_roles
        ");
    }
}

return new CreateUserCompanyRolesTable();