<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_code_aliases', function (Blueprint $table) {
            $table->index(
                ['product_id', 'code_type', 'brand_name', 'code'],
                'product_code_aliases_product_type_brand_code_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('product_code_aliases', function (Blueprint $table) {
            $table->dropIndex('product_code_aliases_product_type_brand_code_index');
        });
    }
};
