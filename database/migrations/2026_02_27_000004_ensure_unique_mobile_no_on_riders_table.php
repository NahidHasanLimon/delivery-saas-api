<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('riders') || ! Schema::hasColumn('riders', 'mobile_no')) {
            return;
        }

        if (! $this->hasUniqueIndex('riders', 'mobile_no')) {
            Schema::table('riders', function (Blueprint $table) {
                $table->unique('mobile_no', 'riders_mobile_no_unique');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left blank. This migration only enforces an invariant.
    }

    private function hasUniqueIndex(string $table, string $column): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $rows = DB::select(
                'SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$database, $table]
            );

            foreach ($rows as $row) {
                if ($row->COLUMN_NAME === $column && (int) $row->NON_UNIQUE === 0) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if ((int) ($index->unique ?? 0) !== 1) {
                    continue;
                }

                $indexName = $index->name;
                $columns = DB::select("PRAGMA index_info('{$indexName}')");
                foreach ($columns as $idxCol) {
                    if (($idxCol->name ?? null) === $column) {
                        return true;
                    }
                }
            }

            return false;
        }

        return true;
    }
};
