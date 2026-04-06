<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('deliveries') && Schema::hasColumn('deliveries', 'status')) {
            DB::table('deliveries')->where('status', 'pending')->update(['status' => 'created']);
            if ($driver !== 'sqlite') {
                DB::statement("ALTER TABLE `deliveries` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'created'");
            }
        }

        if (Schema::hasTable('delivery_status_logs') && Schema::hasColumn('delivery_status_logs', 'status')) {
            DB::table('delivery_status_logs')->where('status', 'pending')->update(['status' => 'created']);
            if ($driver !== 'sqlite') {
                DB::statement(
                    "ALTER TABLE `delivery_status_logs` MODIFY COLUMN `status` ENUM('created','assigned','accepted','in_progress','delivered','returned','cancelled') NOT NULL"
                );
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('delivery_status_logs') && Schema::hasColumn('delivery_status_logs', 'status')) {
            DB::table('delivery_status_logs')->where('status', 'created')->update(['status' => 'pending']);
            if ($driver !== 'sqlite') {
                DB::statement(
                    "ALTER TABLE `delivery_status_logs` MODIFY COLUMN `status` ENUM('pending','assigned','in_progress','delivered','returned','cancelled') NOT NULL"
                );
            }
        }

        if (Schema::hasTable('deliveries') && Schema::hasColumn('deliveries', 'status')) {
            DB::table('deliveries')->where('status', 'created')->update(['status' => 'pending']);
            if ($driver !== 'sqlite') {
                DB::statement("ALTER TABLE `deliveries` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'pending'");
            }
        }
    }
};
