<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('o_t_p_verifications', function (Blueprint $table) {
            $table->string('email')->nullable()->after('mobile_no');
            $table->string('user_type')->nullable()->after('purpose');
            $table->string('channel')->nullable()->after('user_type');
            $table->bigInteger('ref_id_or_context_id')->nullable()->after('channel');
            $table->index(['mobile_no', 'email', 'purpose', 'is_verified', 'expires_at'], 'otp_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('o_t_p_verifications', function (Blueprint $table) {
            $table->dropIndex('otp_lookup_index');
            $table->dropColumn(['email', 'user_type', 'channel']);
        });
    }
};
