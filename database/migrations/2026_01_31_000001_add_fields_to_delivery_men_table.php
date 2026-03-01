<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('photo_url');
            $table->timestamp('invited_at')->nullable()->after('is_active');
            $table->timestamp('activated_at')->nullable()->after('invited_at');
            $table->timestamp('last_login_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'invited_at', 'activated_at', 'last_login_at']);
        });
    }
};
