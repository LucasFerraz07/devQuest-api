<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stage_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('statement');
            $table->string('type', ['multiple_choice', 'code']);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');
            $table->string('programming_language')->nullable();
            $table->longText('starter_code')->nullable();
            $table->longText('explanation')->nullable();
            $table->unsignedInteger('points')->default(10);
            $table->unsignedInteger('position');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['stage_id', 'position']);
            $table->index(['stage_id', 'is_published', 'position']);
            $table->index(['type', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
