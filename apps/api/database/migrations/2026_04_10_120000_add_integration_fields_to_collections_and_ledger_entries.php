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
        Schema::table('collections', function (Blueprint $table) {
            $table->string('source_system', 32)->nullable()->after('customer_id');
            $table->string('source_reference', 128)->nullable()->after('source_system');
            $table->string('sync_status', 32)->nullable()->after('source_reference');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->timestamp('last_synced_at')->nullable()->after('sync_error');

            $table->index(['dealer_id', 'source_system'], 'collections_dealer_source_system_index');
            $table->index(['dealer_id', 'sync_status', 'date'], 'collections_dealer_sync_status_date_index');
            $table->unique(
                ['customer_id', 'source_system', 'source_reference'],
                'collections_customer_source_reference_unique'
            );
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('source_system', 32)->nullable()->after('customer_id');
            $table->string('source_reference', 128)->nullable()->after('source_system');
            $table->timestamp('last_synced_at')->nullable()->after('source_reference');

            $table->index(['dealer_id', 'source_system'], 'ledger_entries_dealer_source_system_index');
            $table->unique(
                ['customer_id', 'source_system', 'source_reference'],
                'ledger_entries_customer_source_reference_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropUnique('ledger_entries_customer_source_reference_unique');
            $table->dropIndex('ledger_entries_dealer_source_system_index');
            $table->dropColumn(['source_system', 'source_reference', 'last_synced_at']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropUnique('collections_customer_source_reference_unique');
            $table->dropIndex('collections_dealer_sync_status_date_index');
            $table->dropIndex('collections_dealer_source_system_index');
            $table->dropColumn(['source_system', 'source_reference', 'sync_status', 'sync_error', 'last_synced_at']);
        });
    }
};
