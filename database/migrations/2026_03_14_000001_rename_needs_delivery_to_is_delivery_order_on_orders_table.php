<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'needs_delivery') || Schema::hasColumn('orders', 'is_delivery_order')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_delivery_order')->default(false)->after('customer_id');
        });

        DB::table('orders')
            ->select('id', 'needs_delivery')
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['is_delivery_order' => (bool) $order->needs_delivery]);
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_needs_delivery');
            $table->dropColumn('needs_delivery');
            $table->index('is_delivery_order', 'idx_orders_is_delivery_order');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'is_delivery_order') || Schema::hasColumn('orders', 'needs_delivery')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('needs_delivery')->default(false)->after('customer_id');
        });

        DB::table('orders')
            ->select('id', 'is_delivery_order')
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['needs_delivery' => (bool) $order->is_delivery_order]);
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_is_delivery_order');
            $table->dropColumn('is_delivery_order');
            $table->index('needs_delivery', 'idx_orders_needs_delivery');
        });
    }
};
