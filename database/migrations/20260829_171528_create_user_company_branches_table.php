<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateUserCompanyBranchesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE user_company_branches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                user_company_role_id BIGINT UNSIGNED NOT NULL,

                branch_id BIGINT UNSIGNED NOT NULL,

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_user_company_branches_role_branch
                    (user_company_role_id, branch_id),

                KEY idx_user_company_branches_role
                    (user_company_role_id),

                KEY idx_user_company_branches_branch
                    (branch_id),

                CONSTRAINT fk_user_company_branches_role
                    FOREIGN KEY (user_company_role_id)
                    REFERENCES user_company_roles(id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,

                CONSTRAINT fk_user_company_branches_branch
                    FOREIGN KEY (branch_id)
                    REFERENCES branches(id)
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
            DROP TABLE IF EXISTS user_company_branches
        ");
    }
}

return new CreateUserCompanyBranchesTable();