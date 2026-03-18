<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameTableIfNeeded('delivery_men', 'riders');
        $this->renameTableIfNeeded('company_delivery_man', 'company_rider');
        $this->renameTableIfNeeded('company_delivery_man_invites', 'company_rider_invites');

        $this->renameColumnIfNeeded('deliveries', 'delivery_man_id', 'rider_id');
        $this->renameColumnIfNeeded('company_rider', 'delivery_man_id', 'rider_id');
        $this->renameColumnIfNeeded('company_rider_invites', 'delivery_man_id', 'rider_id');

        $this->renameIndexIfNeeded(
            'company_rider',
            'company_delivery_man_company_id_delivery_man_id_unique',
            'company_rider_company_id_rider_id_unique'
        );
        $this->renameIndexIfNeeded(
            'deliveries',
            'deliveries_company_id_delivery_man_id_index',
            'deliveries_company_id_rider_id_index'
        );
    }

    public function down(): void
    {
        $this->renameIndexIfNeeded(
            'deliveries',
            'deliveries_company_id_rider_id_index',
            'deliveries_company_id_delivery_man_id_index'
        );
        $this->renameIndexIfNeeded(
            'company_rider',
            'company_rider_company_id_rider_id_unique',
            'company_delivery_man_company_id_delivery_man_id_unique'
        );

        $this->renameColumnIfNeeded('company_rider_invites', 'rider_id', 'delivery_man_id');
        $this->renameColumnIfNeeded('company_rider', 'rider_id', 'delivery_man_id');
        $this->renameColumnIfNeeded('deliveries', 'rider_id', 'delivery_man_id');

        $this->renameTableIfNeeded('company_rider_invites', 'company_delivery_man_invites');
        $this->renameTableIfNeeded('company_rider', 'company_delivery_man');
        $this->renameTableIfNeeded('riders', 'delivery_men');
    }

    private function renameTableIfNeeded(string $from, string $to): void
    {
        if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameColumnIfNeeded(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }
    }

    private function renameIndexIfNeeded(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $from) || $this->hasIndex($table, $to)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($from, $to) {
            $table->renameIndex($from, $to);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $rows = DB::table('information_schema.statistics')
                ->select('INDEX_NAME')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->limit(1)
                ->get();

            return $rows->isNotEmpty();
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
