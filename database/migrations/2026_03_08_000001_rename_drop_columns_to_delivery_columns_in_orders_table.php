<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'drop_contact_name') && ! Schema::hasColumn('orders', 'delivery_contact_name')) {
            DB::statement('ALTER TABLE `orders` CHANGE `drop_contact_name` `delivery_contact_name` VARCHAR(128) NULL');
        }
        if (Schema::hasColumn('orders', 'drop_mobile_number') && ! Schema::hasColumn('orders', 'delivery_mobile_number')) {
            DB::statement('ALTER TABLE `orders` CHANGE `drop_mobile_number` `delivery_mobile_number` VARCHAR(32) NULL');
        }
        if (Schema::hasColumn('orders', 'drop_address') && ! Schema::hasColumn('orders', 'delivery_address')) {
            DB::statement('ALTER TABLE `orders` CHANGE `drop_address` `delivery_address` TEXT NULL');
        }
        if (Schema::hasColumn('orders', 'drop_area') && ! Schema::hasColumn('orders', 'delivery_area')) {
            DB::statement('ALTER TABLE `orders` CHANGE `drop_area` `delivery_area` VARCHAR(128) NULL');
        }
        if (Schema::hasColumn('orders', 'drop_latitude') && ! Schema::hasColumn('orders', 'delivery_latitude')) {
            DB::statement('ALTER TABLE `orders` CHANGE `drop_latitude` `delivery_latitude` DECIMAL(10,7) NULL');
        }
        if (Schema::hasColumn('orders', 'drop_longitude') && ! Schema::hasColumn('orders', 'delivery_longitude')) {
            DB::statement('ALTER TABLE `orders` CHANGE `drop_longitude` `delivery_longitude` DECIMAL(10,7) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'delivery_contact_name') && ! Schema::hasColumn('orders', 'drop_contact_name')) {
            DB::statement('ALTER TABLE `orders` CHANGE `delivery_contact_name` `drop_contact_name` VARCHAR(128) NULL');
        }
        if (Schema::hasColumn('orders', 'delivery_mobile_number') && ! Schema::hasColumn('orders', 'drop_mobile_number')) {
            DB::statement('ALTER TABLE `orders` CHANGE `delivery_mobile_number` `drop_mobile_number` VARCHAR(32) NULL');
        }
        if (Schema::hasColumn('orders', 'delivery_address') && ! Schema::hasColumn('orders', 'drop_address')) {
            DB::statement('ALTER TABLE `orders` CHANGE `delivery_address` `drop_address` TEXT NULL');
        }
        if (Schema::hasColumn('orders', 'delivery_area') && ! Schema::hasColumn('orders', 'drop_area')) {
            DB::statement('ALTER TABLE `orders` CHANGE `delivery_area` `drop_area` VARCHAR(128) NULL');
        }
        if (Schema::hasColumn('orders', 'delivery_latitude') && ! Schema::hasColumn('orders', 'drop_latitude')) {
            DB::statement('ALTER TABLE `orders` CHANGE `delivery_latitude` `drop_latitude` DECIMAL(10,7) NULL');
        }
        if (Schema::hasColumn('orders', 'delivery_longitude') && ! Schema::hasColumn('orders', 'drop_longitude')) {
            DB::statement('ALTER TABLE `orders` CHANGE `delivery_longitude` `drop_longitude` DECIMAL(10,7) NULL');
        }
    }
};

