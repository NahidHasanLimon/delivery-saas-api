<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id()->comment('Primary key');
            $table->unsignedBigInteger('company_id')->comment('Owning business/company id');
            $table->string('order_number', 64)->comment('Business-visible unique order number per company');
            $table->unsignedBigInteger('customer_id')->comment('Reference to customers table');

            $table->string('order_type', 32)->comment('Order type such as delivery or counter');
            $table->string('delivery_medium', 32)->nullable()->comment('Delivery medium such as self, third_party');
            $table->string('status', 32)->comment('Business order status such as new, confirmed, completed, cancelled, returned');
            $table->string('delivery_status', 32)->nullable()->comment('Delivery summary status such as pending, assigned, in_progress, delivered, failed');

            $table->string('delivery_contact_name', 128)->nullable()->comment('Recipient/contact person name at delivery');
            $table->string('delivery_mobile_number', 32)->nullable()->comment('Recipient/contact mobile number');
            $table->text('delivery_address')->nullable()->comment('Delivery address; nullable for counter orders');
            $table->string('delivery_area', 128)->nullable()->comment('Delivery area/thana/zone');
            $table->decimal('delivery_latitude', 10, 7)->nullable()->comment('Delivery latitude');
            $table->decimal('delivery_longitude', 10, 7)->nullable()->comment('Delivery longitude');

            $table->decimal('amount', 12, 2)->default(0)->comment('Total order amount');
            $table->string('payment_method', 32)->comment('Payment method such as cash, online, cod');
            $table->string('payment_status', 32)->comment('Payment status such as unpaid, partial, paid');
            $table->decimal('paid_amount', 12, 2)->default(0)->comment('Amount already paid');
            $table->decimal('collectible_amount', 12, 2)->default(0)->comment('Amount expected to be collected from customer');

            $table->string('note', 255)->nullable()->comment('General note or delivery instruction');
            $table->string('internal_note', 255)->nullable()->comment('Internal business-only note');

            $table->unsignedBigInteger('assigned_delivery_man_id')->nullable()->comment('Currently assigned delivery man if own delivery is used');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User id who created the order');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('User id who last updated the order');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'order_number'], 'uniq_company_order_number');
            $table->index('company_id', 'idx_orders_company_id');
            $table->index('customer_id', 'idx_orders_customer_id');
            $table->index('order_type', 'idx_orders_order_type');
            $table->index('delivery_medium', 'idx_orders_delivery_medium');
            $table->index('status', 'idx_orders_status');
            $table->index('delivery_status', 'idx_orders_delivery_status');
            $table->index('payment_method', 'idx_orders_payment_method');
            $table->index('payment_status', 'idx_orders_payment_status');
            $table->index('assigned_delivery_man_id', 'idx_orders_assigned_delivery_man_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
