<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index(); // redundant to make query faster
            $table->unsignedBigInteger('delivery_id')->index();
             $table->unsignedBigInteger('item_id')->index();
            $table->integer('quantity')->default(1);
            $table->string('notes')->nullable(); // optional
            $table->timestamps();
            $table->unique(['delivery_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
