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
        Schema::create('challenge_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->onDelete('cascade');
            $table->foreignId('participant_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->text('solution_approach');
            $table->text('implementation_plan')->nullable();
            $table->text('expected_impact')->nullable();
            $table->json('attachments')->nullable();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'evaluated', 'winner', 'archived'])->default('draft');
            $table->decimal('score', 5, 2)->nullable();
            $table->integer('ranking')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            // Ensure one submission per user per challenge
            $table->unique(['challenge_id', 'participant_id']);
            
            // Indexes for performance
            $table->index(['challenge_id', 'status']);
            $table->index(['participant_id', 'submitted_at']);
            $table->index(['ranking', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_submissions');
    }
};
