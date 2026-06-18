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
            $table->string('city', 120)->nullable()->after('phone');
            $table->string('district', 120)->nullable()->after('city');

            $table->index(['dealer_id', 'id'], 'customers_dealer_id_id_index');
            $table->index(['dealer_id', 'city', 'district'], 'customers_dealer_city_district_index');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->index(
                ['customer_id', 'order_id', 'entry_type'],
                'ledger_entries_customer_order_type_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex('ledger_entries_customer_order_type_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_dealer_city_district_index');
            $table->dropIndex('customers_dealer_id_id_index');
            $table->dropColumn(['city', 'district']);
        });
    }
};
