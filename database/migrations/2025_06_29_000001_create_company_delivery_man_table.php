<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_delivery_man', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('delivery_man_id');
            $table->timestamps();
            $table->unique(['company_id', 'delivery_man_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_delivery_man');
    }
};
