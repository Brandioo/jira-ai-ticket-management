<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('estimation_type', ['story_points', 'hours'])
                ->default('story_points')
                ->after('priority');
            $table->decimal('estimated_hours', 6, 2)
                ->nullable()
                ->after('story_points');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['estimated_hours', 'estimation_type']);
        });
    }
};
