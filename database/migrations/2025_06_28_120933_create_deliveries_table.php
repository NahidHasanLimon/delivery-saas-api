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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->unsignedBigInteger('customer_id');

           // Optional FK to saved address (not enforced as FK for now)
            $table->unsignedBigInteger('pickup_address_id')->nullable();
            $table->unsignedBigInteger('drop_address_id')->nullable();

            // Snapshot of pickup
            $table->string('pickup_label')->nullable(); 
            $table->text('pickup_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();

            // Snapshot of drop
            $table->string('drop_label')->nullable(); 
            $table->text('drop_address');
            $table->decimal('drop_latitude', 10, 7)->nullable();
            $table->decimal('drop_longitude', 10, 7)->nullable();


            $table->text('delivery_notes')->nullable();  // ✅ useful for internal or user instructions
            $table->string('delivery_type')->nullable(); // e.g., 'order', 'return', 'pickup'
            $table->timestamp('expected_delivery_time')->nullable(); // ✅ allows SLA / delivery windows
            $table->string('delivery_method'); // manual, own, third_party

            $table->enum('status', ['pending', 'assigned', 'in_progress', 'delivered', 'cancelled'])->default('pending'); // ✅ core flow

            $table->text('proof_notes')->nullable();     // ✅ e.g., “Left at door”
            $table->string('proof_image_url')->nullable(); // ✅ delivery photo evidence

            $table->timestamp('assigned_at')->nullable();  // ✅ when it was assigned to delivery man
            $table->timestamp('delivered_at')->nullable(); // ✅ when it was completed
            $table->timestamp('in_progress_at')->nullable(); 
            
            $table->decimal('amount', 12, 2)->nullable(); // 💰 delivery revenue/price

            $table->timestamps(); // ✅ created_at, updated_at

            $table->index(['company_id', 'delivery_man_id']);
            $table->index('status');
            $table->index('tracking_number');
            
            $table->softDeletes(); // 🔄 optional but highly recommended

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
