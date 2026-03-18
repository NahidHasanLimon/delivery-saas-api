<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        DB::table('orders')
            ->where('status', 'new')
            ->update(['status' => 'created']);

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 32)->default('created')->comment('Business order status such as created, confirmed, completed, cancelled, returned')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        DB::table('orders')
            ->where('status', 'created')
            ->update(['status' => 'new']);

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 32)->default('new')->comment('Business order status such as new, confirmed, completed, cancelled, returned')->change();
        });
    }
};
