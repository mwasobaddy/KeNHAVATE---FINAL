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
                'login_success',
                'logout',
                'idea_submission',
                'idea_draft_saved',
                'idea_updated',
                'challenge_creation',
                'challenge_participation',
                'collaboration_invitation',
                'collaboration_invited',
                'collaboration_request',
                'collaboration_removed',
                'collaboration_role_updated',
                'collaboration_accepted',
                'collaboration_declined',
                'account_banning',
                'account_reporting',
                'review_submission',
                'status_change',
                'role_assignment',
                'permission_change',
                'role_created',
                'role_updated',
                'role_deleted',
                'role_removed',
                'user_created',
                'user_updated',
                'user_deleted',
                'otp_generated',
                'otp_resent',
                'otp_verified',
                'otp_validated',
                'otp_failed',
                'otp_validation_failed',
                'new_device_login',
                'device_trusted',
                'password_change',
                'terms_accepted',
                'terms_disagreed',
                'comment_created',
                'comment_reply_created',
                'comment_deleted',
                'comment_updated',
            ]);
            $table->string('entity_type')->nullable(); // Model class name
            $table->unsignedBigInteger('entity_id')->nullable(); // Model ID
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
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
