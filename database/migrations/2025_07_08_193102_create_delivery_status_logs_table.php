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
        Schema::create('delivery_status_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('delivery_id')->index();
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'delivered', 'cancelled']);

            // Who triggered the change
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->string('changed_by_type')->nullable(); // e.g., 'CompanyUser', 'DeliveryMan', 'SystemAdmin'

            // Optional reason/message for the status change
            $table->text('remarks')->nullable();

            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();

            // Optional: index for querying all changes by user
            $table->index(['changed_by_id', 'changed_by_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_status_logs');
    }
};
