<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('selected_option_id')->nullable()->constrained('exercise_options')->nullOnDelete();
            $table->longText('submitted_code')->nullable();
            $table->longText('execution_output')->nullable();
            $table->longText('execution_error')->nullable();
            $table->enum('status', ['pending', 'correct', 'incorrect', 'error']);
            $table->unsignedInteger('score_awarded')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'exercise_id', 'submitted_at']);
            $table->index(['exercise_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_submissions');
    }
};
