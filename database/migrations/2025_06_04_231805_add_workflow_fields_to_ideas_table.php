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
        Schema::table('ideas', function (Blueprint $table) {
            $table->timestamp('last_stage_change')->nullable()->after('current_stage');
            $table->foreignId('last_reviewer_id')->nullable()->constrained('users')->after('last_stage_change');
            $table->timestamp('implementation_started_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropForeign(['last_reviewer_id']);
            $table->dropColumn(['last_stage_change', 'last_reviewer_id', 'implementation_started_at']);
        });
    }
};
