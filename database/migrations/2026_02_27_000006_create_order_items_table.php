<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->unsignedBigInteger('company_id')->comment('Owning business/company id');
            $table->unsignedBigInteger('order_id')->comment('Reference to orders table');
            $table->unsignedBigInteger('item_id')->comment('Reference to items table');
            $table->string('item_name', 255)->comment('Snapshot of item name at order time');
            $table->string('unit', 64)->nullable()->comment('Snapshot of item unit at order time');
            $table->integer('quantity')->default(1)->comment('Ordered quantity');
            $table->string('notes', 255)->nullable()->comment('Optional note for this line item');
            $table->timestamps();

            $table->index('company_id', 'idx_order_items_company_id');
            $table->index('order_id', 'idx_order_items_order_id');
            $table->index('item_id', 'idx_order_items_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

