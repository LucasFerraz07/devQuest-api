<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_test_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exercise_id')->constrained()->cascadeOnDelete();
            $table->longText('input')->nullable();
            $table->longText('expected_output');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['exercise_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_test_cases');
    }
};
