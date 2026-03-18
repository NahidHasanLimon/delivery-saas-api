<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_status_logs') || ! Schema::hasColumn('delivery_status_logs', 'status')) {
            return;
        }

        Schema::table('delivery_status_logs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'delivered', 'returned', 'cancelled'])->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_status_logs') || ! Schema::hasColumn('delivery_status_logs', 'status')) {
            return;
        }

        Schema::table('delivery_status_logs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'delivered', 'cancelled'])->change();
        });
    }
};
