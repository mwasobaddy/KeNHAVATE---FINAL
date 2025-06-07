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
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->text('problem_statement');
            $table->text('evaluation_criteria');
            $table->json('prizes')->nullable(); // JSON for multiple prize tiers
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['draft', 'active', 'closed', 'archived'])->default('draft');
            $table->timestamp('submission_deadline');
            $table->timestamp('evaluation_deadline');
            $table->timestamp('announcement_date')->nullable();
            $table->integer('max_participants')->nullable();
            $table->integer('current_participants')->default(0);
            $table->json('attachments')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['created_by', 'status']);
            $table->index(['status', 'submission_deadline']);
            $table->index(['submission_deadline', 'evaluation_deadline']);
            // Note: SQLite doesn't support fulltext indexes
            // $table->fullText(['title', 'description', 'problem_statement']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
