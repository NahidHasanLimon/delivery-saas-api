<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_delivery_man')) {
            return;
        }

        Schema::table('company_delivery_man', function (Blueprint $table) {
            if (! Schema::hasColumn('company_delivery_man', 'status')) {
                $table->string('status')->default('active')->after('delivery_man_id');
            }

            if (! Schema::hasColumn('company_delivery_man', 'joined_at')) {
                $table->timestamp('joined_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('company_delivery_man')) {
            return;
        }

        Schema::table('company_delivery_man', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('company_delivery_man', 'status')) {
                $dropColumns[] = 'status';
            }

            if (Schema::hasColumn('company_delivery_man', 'joined_at')) {
                $dropColumns[] = 'joined_at';
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

