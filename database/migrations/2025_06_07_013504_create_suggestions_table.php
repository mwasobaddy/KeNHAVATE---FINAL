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
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->text('suggested_changes');
            $table->text('rationale')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('suggestable_type');
            $table->unsignedBigInteger('suggestable_id');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'implemented'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('implementation_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('implemented_at')->nullable();
            $table->unsignedInteger('upvotes')->default(0);
            $table->unsignedInteger('downvotes')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['suggestable_type', 'suggestable_id']);
            $table->index(['author_id']);
            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
