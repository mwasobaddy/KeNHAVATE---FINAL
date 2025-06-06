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
        Schema::create('appeal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('appeal_type', ['ban', 'suspension']); // Type of appeal
            $table->text('message'); // User's appeal message
            $table->enum('status', ['pending', 'reviewed', 'approved', 'rejected'])->default('pending');
            $table->text('admin_response')->nullable(); // Admin's response to the appeal
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // Admin who reviewed
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('last_sent_at'); // When this appeal was sent
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'appeal_type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['last_sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appeal_messages');
    }
};
