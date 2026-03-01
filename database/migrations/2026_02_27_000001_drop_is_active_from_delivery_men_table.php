<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('delivery_men', 'is_active')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('delivery_men', 'is_active')) {
            Schema::table('delivery_men', function (Blueprint $table) {
                $table->boolean('is_active')->default(false)->after('status');
            });
        }
    }
};
