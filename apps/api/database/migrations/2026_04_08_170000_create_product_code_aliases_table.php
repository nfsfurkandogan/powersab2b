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
        Schema::create('product_code_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('normalized_code');
            $table->string('code_type', 32)->default('other');
            $table->string('brand_name')->nullable();
            $table->string('source', 32)->default('manual');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('normalized_code');
            $table->index(['code_type', 'normalized_code'], 'product_code_aliases_type_normalized_index');
            $table->index(['source', 'product_id'], 'product_code_aliases_source_product_index');
            $table->unique(
                ['product_id', 'normalized_code', 'code_type', 'source'],
                'product_code_aliases_product_normalized_type_source_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_code_aliases');
    }
};
