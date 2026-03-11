<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('needs_delivery')->default(false)->after('customer_id');
            $table->string('order_source', 50)->nullable()->after('needs_delivery');
            $table->decimal('subtotal_amount', 12, 2)->default(0)->after('delivery_longitude');
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('subtotal_amount');
            $table->decimal('adjustment_amount', 12, 2)->default(0)->after('delivery_fee');
            $table->decimal('total_amount', 12, 2)->default(0)->after('adjustment_amount');
        });

        DB::table('orders')
            ->where('order_type', 'delivery')
            ->update(['needs_delivery' => true]);

        DB::table('orders')->update([
            'subtotal_amount' => DB::raw('COALESCE(amount, 0)'),
            'total_amount' => DB::raw('COALESCE(amount, 0)'),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_order_type');
            $table->dropIndex('idx_orders_delivery_medium');
            $table->dropIndex('idx_orders_assigned_delivery_man_id');

            $table->dropColumn([
                'order_type',
                'delivery_medium',
                'amount',
                'assigned_delivery_man_id',
            ]);

            $table->index('needs_delivery', 'idx_orders_needs_delivery');
            $table->index('order_source', 'idx_orders_order_source');
            $table->index('total_amount', 'idx_orders_total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type', 32)->default('counter')->after('customer_id');
            $table->string('delivery_medium', 32)->nullable()->after('order_type');
            $table->decimal('amount', 12, 2)->default(0)->after('delivery_longitude');
            $table->unsignedBigInteger('assigned_delivery_man_id')->nullable()->after('internal_note');
        });

        DB::table('orders')
            ->where('needs_delivery', true)
            ->update(['order_type' => 'delivery']);

        DB::table('orders')
            ->where('needs_delivery', false)
            ->update(['order_type' => 'counter']);

        DB::table('orders')->update([
            'amount' => DB::raw('COALESCE(total_amount, 0)'),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_needs_delivery');
            $table->dropIndex('idx_orders_order_source');
            $table->dropIndex('idx_orders_total_amount');

            $table->dropColumn([
                'needs_delivery',
                'order_source',
                'subtotal_amount',
                'delivery_fee',
                'adjustment_amount',
                'total_amount',
            ]);

            $table->index('order_type', 'idx_orders_order_type');
            $table->index('delivery_medium', 'idx_orders_delivery_medium');
            $table->index('assigned_delivery_man_id', 'idx_orders_assigned_delivery_man_id');
        });
    }
};
