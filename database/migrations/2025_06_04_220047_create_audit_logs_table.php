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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('action', [
                'account_creation',
                'login', 
                'logout',
                'idea_submission',
                'challenge_creation',
                'challenge_participation',
                'collaboration_invitation',
                'collaboration_request',
                'account_banning',
                'account_reporting',
                'review_submission',
                'status_change',
                'role_assignment',
                'permission_change'
            ]);
            $table->string('entity_type')->nullable(); // Model class name
            $table->unsignedBigInteger('entity_id')->nullable(); // Model ID
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
