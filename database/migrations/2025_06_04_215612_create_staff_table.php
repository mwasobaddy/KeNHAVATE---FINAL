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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('staff_number')->unique();
            $table->string('job_title');
            $table->string('department');
            $table->string('supervisor_name')->nullable();
            $table->string('work_station');
            $table->date('employment_date');
            $table->enum('employment_type', ['permanent', 'contract', 'temporary']);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'staff_number']);
            $table->index(['department', 'job_title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
