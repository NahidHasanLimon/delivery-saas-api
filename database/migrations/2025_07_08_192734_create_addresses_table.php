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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            // Company context (even if used for customers or deliverymen)
            $table->unsignedBigInteger('company_id')->nullable()->index();

            // Polymorphic relation
            $table->morphs('addressable'); // addressable_id, addressable_type

            $table->string('address_type')->nullable();  // e.g., 'warehouse', 'pickup_point'
            $table->string('label')->nullable();         // Optional: 'Main Office', 'Home'

            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->index(['address_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
