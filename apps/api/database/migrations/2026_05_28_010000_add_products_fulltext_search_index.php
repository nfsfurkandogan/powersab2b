<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('products')) {
            return;
        }

        $database = DB::getDatabaseName();
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'products')
            ->where('index_name', 'products_search_fulltext')
            ->exists();

        if (! $exists) {
            DB::statement('ALTER TABLE products ADD FULLTEXT products_search_fulltext (sku, oem_code, name)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('products')) {
            return;
        }

        $database = DB::getDatabaseName();
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'products')
            ->where('index_name', 'products_search_fulltext')
            ->exists();

        if ($exists) {
            DB::statement('ALTER TABLE products DROP INDEX products_search_fulltext');
        }
    }
};
