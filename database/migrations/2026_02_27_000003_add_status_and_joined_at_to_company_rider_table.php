<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_rider')) {
            return;
        }

        Schema::table('company_rider', function (Blueprint $table) {
            if (! Schema::hasColumn('company_rider', 'status')) {
                $table->string('status')->default('active')->after('rider_id');
            }

            if (! Schema::hasColumn('company_rider', 'joined_at')) {
                $table->timestamp('joined_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('company_rider')) {
            return;
        }

        Schema::table('company_rider', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('company_rider', 'status')) {
                $dropColumns[] = 'status';
            }

            if (Schema::hasColumn('company_rider', 'joined_at')) {
                $dropColumns[] = 'joined_at';
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

