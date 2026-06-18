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
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'id'], 'products_active_id_index');
            $table->index(['is_active', 'brand_id', 'id'], 'products_active_brand_id_index');
            $table->index(['is_active', 'category_id', 'id'], 'products_active_category_id_index');
            $table->index(['sku', 'oem_code'], 'products_sku_oem_index');
        });

        Schema::table('stock_summary', function (Blueprint $table) {
            $table->index(['available_total', 'product_id'], 'stock_summary_available_product_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_summary', function (Blueprint $table) {
            $table->dropIndex('stock_summary_available_product_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_sku_oem_index');
            $table->dropIndex('products_active_category_id_index');
            $table->dropIndex('products_active_brand_id_index');
            $table->dropIndex('products_active_id_index');
        });
    }
};
