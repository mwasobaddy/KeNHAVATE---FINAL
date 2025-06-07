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
        Schema::create('suggestion_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['upvote', 'downvote']);
            $table->timestamps();

            // Ensure user can only vote once per suggestion
            $table->unique(['suggestion_id', 'user_id']);
            
            // Indexes for performance
            $table->index(['suggestion_id', 'type']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestion_votes');
    }
};
