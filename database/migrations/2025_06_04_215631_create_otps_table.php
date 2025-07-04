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
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('otp_code', 6);
            $table->enum('purpose', ['login', 'registration', 'password_reset'])->default('registration');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('validated_ip', 45)->nullable();
            $table->text('validated_user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['email', 'expires_at']);
            $table->index(['otp_code', 'used_at']);
            $table->index(['purpose', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
