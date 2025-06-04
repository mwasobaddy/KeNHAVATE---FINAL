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
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', [
                'status_change',
                'review_assigned', 
                'collaboration_request',
                'deadline_reminder',
                'device_login',
                'points_awarded',
                'challenge_created',
                'submission_received'
            ]);
            $table->string('title');
            $table->text('message');
            $table->morphs('related'); // Related entity (idea, challenge, etc.)
            $table->json('data')->nullable(); // Additional data
            $table->timestamp('read_at')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'read_at']);
            $table->index(['type', 'created_at']);
            // Note: morphs() already creates index for related_type, related_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
