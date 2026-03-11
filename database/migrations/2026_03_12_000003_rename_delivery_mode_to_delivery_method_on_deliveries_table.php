<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `deliveries`
            DROP INDEX `deliveries_delivery_mode_index`,
            CHANGE `delivery_mode` `delivery_method` VARCHAR(255) NOT NULL
        ");

        DB::statement("
            ALTER TABLE `deliveries`
            ADD INDEX `deliveries_delivery_method_index` (`delivery_method`)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `deliveries`
            DROP INDEX `deliveries_delivery_method_index`,
            CHANGE `delivery_method` `delivery_mode` VARCHAR(255) NULL
        ");

        DB::statement("
            ALTER TABLE `deliveries`
            ADD INDEX `deliveries_delivery_mode_index` (`delivery_mode`)
        ");
    }
};
