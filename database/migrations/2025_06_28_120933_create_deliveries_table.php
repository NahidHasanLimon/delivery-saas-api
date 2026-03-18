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
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('delivery_source', 50)->default('standalone');
            $table->unsignedBigInteger('rider_id')->nullable();
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

            $table->text('delivery_notes')->nullable();
            $table->timestamp('expected_delivery_time')->nullable();
            $table->string('delivery_method');
            $table->string('provider_name', 100)->nullable();

            $table->string('status', 50)->default('pending');

            $table->text('proof_notes')->nullable();
            $table->string('proof_image_url')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();

            $table->decimal('collectible_amount', 12, 2)->default(0);
            $table->decimal('collected_amount', 12, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'rider_id']);
            $table->index('order_id', 'deliveries_order_id_index');
            $table->index('delivery_source', 'deliveries_delivery_source_index');
            $table->index('delivery_method', 'deliveries_delivery_method_index');
            $table->index('provider_name', 'deliveries_provider_name_index');
            $table->index('status');
            $table->index('tracking_number');
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
