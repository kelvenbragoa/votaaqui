<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('participations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contestant_id');
            $table->unsignedBigInteger('episode_id');
            $table->text('performance_data')->nullable();
            $table->decimal('jury_score', 4, 2)->nullable();
            $table->unsignedBigInteger('public_votes')->default(0);
            $table->decimal('public_score', 4, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->unsignedBigInteger('episode_position')->nullable();
            $table->boolean('eliminated')->default(false);
            $table->boolean('saved_by_public')->default(false);
            $table->text('judge_comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participations');
    }
};
