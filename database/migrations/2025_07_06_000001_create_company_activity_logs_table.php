<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id')->nullable(); // company_user who performed the action
            $table->string('action'); // e.g., 'delivery_created', 'delivery_assigned', 'customer_created'
            $table->text('description'); // human-readable description
            $table->string('subject_type')->nullable(); // e.g., 'App\Models\Delivery', 'App\Models\Customer'
            $table->unsignedBigInteger('subject_id')->nullable(); // ID of the related model
            $table->json('properties')->nullable(); // additional data (old/new values, etc.)
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'action']);
            $table->index(['subject_type', 'subject_id']);
            
            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('company_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_activity_logs');
    }
};
