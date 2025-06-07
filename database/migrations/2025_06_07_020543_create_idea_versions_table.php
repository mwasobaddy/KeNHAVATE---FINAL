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
        Schema::create('idea_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->onDelete('cascade');
            $table->integer('version_number');
            $table->string('title');
            $table->text('description');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['idea_id', 'version_number']);
            $table->index(['idea_id', 'is_current']);
            $table->unique(['idea_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idea_versions');
    }
};
