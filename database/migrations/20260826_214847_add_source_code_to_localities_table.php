<?php

namespace NovaSysCore\Database\Migrations;

use NovaSysCore\Database\Migration;

class AddSourceCodeToLocalitiesTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE localities
            ADD COLUMN source_code VARCHAR(30) NULL
            AFTER slug
        ");

        $this->execute("
            CREATE INDEX idx_localities_source_code
            ON localities (source_code)
        ");
    }

    public function down(): void
    {
        $this->execute("
            DROP INDEX idx_localities_source_code
            ON localities
        ");

        $this->execute("
            ALTER TABLE localities
            DROP COLUMN source_code
        ");
    }
}

return new AddSourceCodeToLocalitiesTable();