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
        Schema::create('user_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action', [
                'daily_login',
                'account_creation',
                'idea_submission',
                'challenge_participation',
                'collaboration_contribution',
                'review_completion',
                'idea_approved',
                'challenge_winner',
                'bonus_award'
            ]);
            $table->integer('points');
            $table->text('description')->nullable();
            $table->morphs('related'); // Related entity that earned points
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'points']);
            // Note: morphs() already creates index for related_type, related_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_points');
    }
};
