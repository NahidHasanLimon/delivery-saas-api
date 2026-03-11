<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE `deliveries`
            SET `delivery_method` = 'manual'
            WHERE `delivery_method` IS NULL
        ");

        DB::statement("
            ALTER TABLE `deliveries`
            MODIFY COLUMN `delivery_method` VARCHAR(255) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `deliveries`
            MODIFY COLUMN `delivery_method` VARCHAR(255) NULL
        ");
    }
};
