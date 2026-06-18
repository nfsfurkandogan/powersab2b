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
        Schema::create('hot_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('rank')->default(0);
            $table->timestamps();

            $table->unique('product_id');
            $table->index(['rank', 'product_id'], 'hot_products_rank_product_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'created_at', 'id'], 'products_active_created_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_created_id_index');
        });

        Schema::dropIfExists('hot_products');
    }
};
