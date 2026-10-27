<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE tickets MODIFY type ENUM('task','bug','story','epic') NOT NULL DEFAULT 'task'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "UPDATE tickets SET type = 'task' WHERE type = 'epic'"
            );

            DB::statement(
                "ALTER TABLE tickets MODIFY type ENUM('task','bug','story') NOT NULL DEFAULT 'task'"
            );
        }
    }
};
