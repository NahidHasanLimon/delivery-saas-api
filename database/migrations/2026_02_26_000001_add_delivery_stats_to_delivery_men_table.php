<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->string('identification_number', 20)->nullable()->unique()->after('id');
            $table->string('status')->default('active')->after('last_login_at');
            $table->unsignedBigInteger('total_deliveries')->default(0)->after('identification_number');
            $table->unsignedBigInteger('successful_deliveries')->default(0)->after('total_deliveries');
            $table->unsignedBigInteger('cancelled_deliveries')->default(0)->after('successful_deliveries');
            $table->decimal('total_rating_points', 10, 2)->default(0)->after('cancelled_deliveries');
            $table->unsignedBigInteger('total_rating_count')->default(0)->after('total_rating_points');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_men', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'identification_number',
                'total_deliveries',
                'successful_deliveries',
                'cancelled_deliveries',
                'total_rating_points',
                'total_rating_count',
            ]);
        });
    }
};
