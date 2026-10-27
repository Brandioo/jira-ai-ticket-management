<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['task', 'bug', 'story', 'epic'])->default('task');
            $table->enum('status', ['todo', 'in_progress', 'review', 'done'])->default('todo');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->unsignedTinyInteger('story_points')->nullable();
            $table->timestamps();

            $table->index(['sprint_id', 'status']);
            $table->index(['assignee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
