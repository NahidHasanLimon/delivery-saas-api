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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('mobile_no');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('customer_code')->nullable(); // optional company-defined code
            $table->timestamps();

            // Ensure mobile_no is unique per company
            $table->unique(['company_id', 'mobile_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
