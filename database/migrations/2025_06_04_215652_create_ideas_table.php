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
        Schema::create('ideas', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->text('problem_statement')->nullable(); // Made nullable to match form
            $table->text('proposed_solution')->nullable(); // Made nullable to match form
            $table->text('expected_benefits')->nullable();
            $table->text('implementation_plan')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null'); // Made nullable
            $table->enum('current_stage', [
                'draft', 
                'submitted', 
                'manager_review', 
                'sme_review', 
                'collaboration', 
                'board_review', 
                'implementation', 
                'completed', 
                'archived'
            ])->default('draft');

            // Form fields from create.blade.php
            $table->text('business_case')->nullable();
            $table->text('expected_impact')->nullable();
            $table->text('implementation_timeline')->nullable();
            $table->text('resource_requirements')->nullable();

            $table->foreignId('last_reviewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_stage_change')->nullable();
            $table->boolean('collaboration_enabled')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Workflow tracking fields
            $table->timestamp('manager_review_started_at')->nullable();
            $table->timestamp('manager_review_completed_at')->nullable();
            $table->timestamp('sme_review_started_at')->nullable();
            $table->timestamp('sme_review_completed_at')->nullable();
            $table->timestamp('board_review_started_at')->nullable();
            $table->timestamp('board_review_completed_at')->nullable();
            $table->timestamp('collaboration_started_at')->nullable();
            $table->timestamp('implementation_started_at')->nullable();
            $table->foreignId('assigned_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_sme_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('assigned_board_member_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('total_review_time_hours')->default(0);
            $table->json('workflow_metadata')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['author_id', 'current_stage']);
            $table->index(['current_stage', 'submitted_at']);
            $table->index(['created_at']);
            // Note: SQLite doesn't support fulltext indexes
            // $table->fullText(['title', 'description', 'problem_statement']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ideas');
    }
};
