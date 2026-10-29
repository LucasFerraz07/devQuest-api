<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exercise_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['exercise_id', 'position']);
            $table->index(['exercise_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_options');
    }
};
