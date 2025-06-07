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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->unsignedInteger('upvotes')->default(0);
            $table->unsignedInteger('downvotes')->default(0);
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['commentable_type', 'commentable_id']);
            $table->index(['parent_id']);
            $table->index(['author_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
