<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('learning_path_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->unsignedSmallInteger('minimum_score')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['learning_path_id', 'position']);
            $table->index(['learning_path_id', 'is_published', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
