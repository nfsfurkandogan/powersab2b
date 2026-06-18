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
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->index(
                ['dealer_id', 'customer_id', 'date', 'id'],
                'ledger_entries_dealer_customer_date_id_idx'
            );
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(
                ['dealer_id', 'status', 'customer_id', 'ordered_at', 'id'],
                'orders_dealer_status_customer_ordered_id_idx'
            );
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(
                ['order_id', 'product_id', 'line_total', 'tax_rate', 'quantity'],
                'order_items_order_product_line_tax_qty_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_product_line_tax_qty_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_dealer_status_customer_ordered_id_idx');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex('ledger_entries_dealer_customer_date_id_idx');
        });
    }
};
