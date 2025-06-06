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
        Schema::create('challenge_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_submission_id')->constrained('challenge_submissions')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->enum('stage', ['manager_review', 'sme_review', 'board_review', 'final_review'])->default('manager_review');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->json('criteria_scores')->nullable(); // Detailed scoring per criterion
            $table->text('feedback')->nullable(); // General feedback
            $table->enum('recommendation', ['approve', 'reject', 'needs_revision', 'pending'])->default('pending');
            $table->json('strengths_weaknesses')->nullable(); // Structured feedback
            $table->text('decision')->nullable(); // Final decision reasoning
            $table->timestamp('reviewed_at')->nullable(); // When review was completed
            $table->text('review_notes')->nullable(); // Internal reviewer notes
            $table->integer('time_spent_minutes')->nullable(); // Time spent on review
            $table->timestamps();
            
            // Prevent duplicate reviews by same reviewer for same submission
            $table->unique(['challenge_submission_id', 'reviewer_id'], 'unique_challenge_reviewer');
            
            // Indexes for performance
            $table->index(['challenge_submission_id', 'stage']);
            $table->index(['reviewer_id', 'reviewed_at']);
            $table->index(['recommendation', 'overall_score']);
            $table->index(['stage', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_reviews');
    }
};
