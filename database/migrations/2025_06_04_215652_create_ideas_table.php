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
            $table->text('problem_statement');
            $table->text('proposed_solution');
            $table->text('expected_benefits')->nullable();
            $table->text('implementation_plan')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('category')->nullable();
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
            $table->boolean('collaboration_enabled')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
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
