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
        Schema::table('carts', function (Blueprint $table) {
            $table->string('shipping_method', 120)->nullable()->after('currency');
            $table->boolean('is_warehouse_transfer')->default(false)->after('shipping_method');
            $table->text('order_note')->nullable()->after('note');

            $table->index(
                ['dealer_id', 'customer_id', 'status', 'updated_at'],
                'carts_dealer_customer_status_updated_index'
            );
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(20.00)->after('discount_rate');
            $table->index(['cart_id', 'quantity'], 'cart_items_cart_quantity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_quantity_index');
            $table->dropColumn('vat_rate');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropIndex('carts_dealer_customer_status_updated_index');
            $table->dropColumn(['shipping_method', 'is_warehouse_transfer', 'order_note']);
        });
    }
};
