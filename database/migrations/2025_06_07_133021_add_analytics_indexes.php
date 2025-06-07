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
        // Add indexes for analytics performance optimization
        
        // Ideas table indexes for analytics queries
        Schema::table('ideas', function (Blueprint $table) {
            $table->index(['current_stage', 'created_at'], 'ideas_stage_created_idx');
            $table->index(['author_id', 'current_stage'], 'ideas_author_stage_idx');
            $table->index(['category_id', 'created_at'], 'ideas_category_created_idx');
            $table->index(['collaboration_enabled', 'current_stage'], 'ideas_collab_stage_idx');
        });
        
        // Reviews table indexes for performance metrics
        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['reviewable_type', 'reviewable_id'], 'reviews_reviewable_idx');
            $table->index(['reviewer_id', 'created_at'], 'reviews_reviewer_created_idx');
            $table->index(['stage', 'decision'], 'reviews_stage_decision_idx');
            $table->index(['created_at', 'updated_at'], 'reviews_timing_idx');
        });
        
        // Challenges table indexes for analytics
        Schema::table('challenges', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'challenges_status_created_idx');
            $table->index(['created_by', 'status'], 'challenges_creator_status_idx');
            $table->index(['submission_deadline', 'status'], 'challenges_deadline_status_idx');
        });
        
        // Challenge submissions table indexes
        Schema::table('challenge_submissions', function (Blueprint $table) {
            $table->index(['challenge_id', 'created_at'], 'submissions_challenge_created_idx');
            $table->index(['user_id', 'challenge_id'], 'submissions_user_challenge_idx');
            $table->index(['status', 'created_at'], 'submissions_status_created_idx');
        });
        
        // User points table indexes for gamification analytics
        Schema::table('user_points', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'points_user_created_idx');
            $table->index(['source_type', 'source_id'], 'points_source_idx');
            $table->index(['points', 'created_at'], 'points_amount_created_idx');
        });
        
        // Audit logs table indexes for tracking analytics
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'audit_user_created_idx');
            $table->index(['action', 'created_at'], 'audit_action_created_idx');
            $table->index(['entity_type', 'entity_id'], 'audit_entity_idx');
        });
        
        // Collaborations table indexes
        Schema::table('collaborations', function (Blueprint $table) {
            $table->index(['idea_id', 'status'], 'collab_idea_status_idx');
            $table->index(['user_id', 'created_at'], 'collab_user_created_idx');
            $table->index(['status', 'created_at'], 'collab_status_created_idx');
        });
        
        // Notifications table indexes for system metrics
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'type'], 'notif_user_type_idx');
            $table->index(['created_at', 'read_at'], 'notif_timing_idx');
            $table->index(['type', 'created_at'], 'notif_type_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop analytics indexes
        
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropIndex('ideas_stage_created_idx');
            $table->dropIndex('ideas_author_stage_idx');
            $table->dropIndex('ideas_category_created_idx');
            $table->dropIndex('ideas_collab_stage_idx');
        });
        
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_reviewable_idx');
            $table->dropIndex('reviews_reviewer_created_idx');
            $table->dropIndex('reviews_stage_decision_idx');
            $table->dropIndex('reviews_timing_idx');
        });
        
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropIndex('challenges_status_created_idx');
            $table->dropIndex('challenges_creator_status_idx');
            $table->dropIndex('challenges_deadline_status_idx');
        });
        
        Schema::table('challenge_submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_challenge_created_idx');
            $table->dropIndex('submissions_user_challenge_idx');
            $table->dropIndex('submissions_status_created_idx');
        });
        
        Schema::table('user_points', function (Blueprint $table) {
            $table->dropIndex('points_user_created_idx');
            $table->dropIndex('points_source_idx');
            $table->dropIndex('points_amount_created_idx');
        });
        
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_user_created_idx');
            $table->dropIndex('audit_action_created_idx');
            $table->dropIndex('audit_entity_idx');
        });
        
        Schema::table('collaborations', function (Blueprint $table) {
            $table->dropIndex('collab_idea_status_idx');
            $table->dropIndex('collab_user_created_idx');
            $table->dropIndex('collab_status_created_idx');
        });
        
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notif_user_type_idx');
            $table->dropIndex('notif_timing_idx');
            $table->dropIndex('notif_type_created_idx');
        });
    }
};
