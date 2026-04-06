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

        if (! Schema::hasColumn('orders', 'collectible_amount') || Schema::hasColumn('orders', 'due_amount')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `orders` CHANGE COLUMN `collectible_amount` `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount still due from customer'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'due_amount') || Schema::hasColumn('orders', 'collectible_amount')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `orders` CHANGE COLUMN `due_amount` `collectible_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount expected to be collected from customer'"
        );
    }
};

