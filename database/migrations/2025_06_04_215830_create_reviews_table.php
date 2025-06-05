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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->morphs('reviewable'); // For ideas or challenge submissions
            $table->enum('review_stage', ['manager_review', 'sme_review', 'board_review', 'challenge_review']);
            $table->enum('decision', ['approved', 'rejected', 'needs_changes', 'pending'])->default('pending');
            $table->text('comments')->nullable();
            $table->text('feedback')->nullable();
            $table->json('criteria_scores')->nullable(); // For detailed scoring
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['reviewer_id', 'review_stage']);
            // Note: morphs() already creates index for reviewable_type, reviewable_id
            $table->index(['decision', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
