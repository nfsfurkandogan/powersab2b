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
            $table->index(['dealer_id', 'tax_number'], 'customers_dealer_tax_number_index');
            $table->index(['dealer_id', 'email'], 'customers_dealer_email_index');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->index(
                ['customer_id', 'entry_type', 'entry_date'],
                'ledger_entries_customer_type_date_index'
            );
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index(
                ['customer_id', 'method', 'collection_date'],
                'collections_customer_method_date_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_customer_method_date_index');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex('ledger_entries_customer_type_date_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_dealer_email_index');
            $table->dropIndex('customers_dealer_tax_number_index');
        });
    }
};
