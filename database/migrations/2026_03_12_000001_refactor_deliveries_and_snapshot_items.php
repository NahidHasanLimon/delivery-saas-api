<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `deliveries`
            MODIFY COLUMN `status` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending'
        ");

        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('company_id');
            $table->string('delivery_source', 50)->default('standalone')->after('order_id');
            $table->string('provider_name', 100)->nullable()->after('delivery_method');
            $table->decimal('collectible_amount', 12, 2)->default(0)->after('amount');
            $table->decimal('collected_amount', 12, 2)->default(0)->after('collectible_amount');
            $table->timestamp('picked_at')->nullable()->after('assigned_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            $table->dropColumn('delivery_type');

            $table->index('order_id', 'deliveries_order_id_index');
            $table->index('delivery_source', 'deliveries_delivery_source_index');
            $table->index('delivery_method', 'deliveries_delivery_method_index');
            $table->index('provider_name', 'deliveries_provider_name_index');
        });

        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropUnique('delivery_items_delivery_id_item_id_unique');
            $table->string('item_name')->after('item_id');
            $table->string('unit', 64)->nullable()->after('item_name');
            $table->decimal('unit_price', 12, 2)->default(0)->after('unit');
            $table->decimal('line_total', 12, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'unit', 'unit_price', 'line_total']);
            $table->unique(['delivery_id', 'item_id'], 'delivery_items_delivery_id_item_id_unique');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_order_id_index');
            $table->dropIndex('deliveries_delivery_source_index');
            $table->dropIndex('deliveries_delivery_method_index');
            $table->dropIndex('deliveries_provider_name_index');

            $table->dropColumn([
                'order_id',
                'delivery_source',
                'provider_name',
                'collectible_amount',
                'collected_amount',
                'picked_at',
                'cancelled_at',
            ]);

            $table->string('delivery_type')->nullable()->after('delivery_notes');
        });

        DB::statement("
            ALTER TABLE `deliveries`
            MODIFY COLUMN `status` ENUM('pending', 'assigned', 'in_progress', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'
        ");
    }
};
