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
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('source_system', 32)->nullable()->after('salesperson_user_id');
            $table->string('source_reference', 128)->nullable()->after('source_system');
            $table->timestamp('last_synced_at')->nullable()->after('updated_at');

            $table->index(['dealer_id', 'source_system'], 'customers_dealer_source_system_index');
            $table->unique(
                ['dealer_id', 'source_system', 'source_reference'],
                'customers_dealer_source_reference_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_dealer_source_reference_unique');
            $table->dropIndex('customers_dealer_source_system_index');
            $table->dropColumn(['source_system', 'source_reference', 'last_synced_at']);
        });
    }
};
