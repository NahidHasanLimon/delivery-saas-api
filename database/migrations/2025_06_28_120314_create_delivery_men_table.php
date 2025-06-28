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
        Schema::create('delivery_men', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile_no')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('photo_url')->nullable();
            $table->timestamps();
        });
    }

    /**             
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_men');
    }
};
