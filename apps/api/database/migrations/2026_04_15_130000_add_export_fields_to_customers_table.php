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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('sync_status', 32)->nullable()->after('source_reference');
            $table->string('sync_error', 2000)->nullable()->after('sync_status');

            $table->index(['dealer_id', 'source_system', 'sync_status'], 'customers_dealer_source_sync_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_dealer_source_sync_status_index');
            $table->dropColumn(['sync_status', 'sync_error']);
        });
    }
};
