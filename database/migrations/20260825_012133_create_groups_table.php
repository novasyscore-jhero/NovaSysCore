<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class CreateGroupsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE groups (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                name VARCHAR(150) NOT NULL,

                slug VARCHAR(160) NOT NULL,

                description TEXT NULL,

                status VARCHAR(30) NOT NULL DEFAULT 'active',

                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uq_groups_slug (slug)
            ) ENGINE=InnoDB
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP TABLE IF EXISTS groups
        ");
    }
}

return new CreateGroupsTable();