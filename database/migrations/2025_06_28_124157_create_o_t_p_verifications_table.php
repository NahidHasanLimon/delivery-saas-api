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
        Schema::create('o_t_p_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_no'); // Who the OTP is sent to
            $table->string('otp_code');  // The OTP itself
            $table->string('purpose')->nullable(); // e.g., 'login', 'registration', 'reset_password'
            $table->ipAddress('ip_address')->nullable(); // Requester IP
            $table->string('user_agent')->nullable(); // Device/browser info
            $table->boolean('is_verified')->default(false); // If OTP was successfully verified
            $table->timestamp('expires_at'); // Expiry time
            $table->timestamp('verified_at')->nullable(); // When it was verified
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('o_t_p_verifications');
    }
};
