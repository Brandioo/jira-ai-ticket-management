<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('worklogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->string('note', 1000)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worklogs');
    }
};
