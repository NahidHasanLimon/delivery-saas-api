<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_rider', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('rider_id');
            $table->timestamps();
            $table->unique(['company_id', 'rider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_rider');
    }
};
