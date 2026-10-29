<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stage_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('locked');
            $table->unsignedSmallInteger('best_score')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'stage_id']);
            $table->index(['user_id', 'status']);
            $table->index(['stage_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stage_progress');
    }
};
