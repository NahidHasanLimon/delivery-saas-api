<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_delivery_man_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mobile_number');
            $table->foreignId('delivery_man_id')->nullable()->constrained('delivery_men')->nullOnDelete();
            $table->string('status')->default('pending'); // pending|verified|expired|canceled
            $table->foreignId('created_by')->nullable()->constrained('company_users')->nullOnDelete();
            $table->timestamps();

            $table->index('mobile_number');
            $table->index(['company_id', 'mobile_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_delivery_man_invites');
    }
};
