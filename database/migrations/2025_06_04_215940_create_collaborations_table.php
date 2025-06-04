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
        Schema::create('collaborations', function (Blueprint $table) {
            $table->id();
            $table->morphs('collaborable'); // For ideas or challenge submissions
            $table->foreignId('collaborator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'declined', 'removed'])->default('pending');
            $table->enum('role', ['contributor', 'co_author', 'reviewer'])->default('contributor');
            $table->text('invitation_message')->nullable();
            $table->text('contribution_summary')->nullable();
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            
            // Ensure one collaboration record per user per idea/submission
            $table->unique(['collaborable_type', 'collaborable_id', 'collaborator_id']);
            
            // Indexes for performance
            $table->index(['collaborator_id', 'status']);
            // Note: morphs() already creates index for collaborable_type, collaborable_id
            $table->index(['invited_by', 'invited_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborations');
    }
};
