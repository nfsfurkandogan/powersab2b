<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->foreignId('price_list_id')
                ->nullable()
                ->after('code')
                ->constrained('price_lists')
                ->nullOnDelete();
        });

        Schema::create('base_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('list_price', 15, 2);
            $table->char('currency', 3)->default('TRY');
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['price_list_id', 'product_id']);
            $table->index(['product_id', 'price_list_id']);
            $table->index(['price_list_id', 'list_price', 'product_id']);
        });

        Schema::create('dealer_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('net_price', 15, 2);
            $table->char('currency', 3)->default('TRY');
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['dealer_id', 'product_id']);
            $table->index(['product_id', 'dealer_id']);
            $table->index(['dealer_id', 'net_price', 'product_id']);
        });

        DB::table('price_lists')->insert([
            [
                'code' => 'A',
                'name' => 'List A',
                'discount_rate' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'B',
                'name' => 'List B',
                'discount_rate' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'C',
                'name' => 'List C',
                'discount_rate' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $defaultPriceListId = DB::table('price_lists')->where('code', 'A')->value('id');

        if ($defaultPriceListId !== null) {
            DB::table('dealers')
                ->whereNull('price_list_id')
                ->update(['price_list_id' => $defaultPriceListId]);
        }

        if (Schema::hasTable('dealer_product_price') && $defaultPriceListId !== null) {
            DB::table('base_prices')->insertUsing(
                ['price_list_id', 'product_id', 'list_price', 'currency', 'updated_at'],
                DB::table('dealer_product_price')
                    ->selectRaw(
                        (int) $defaultPriceListId.' as price_list_id, product_id, MIN(net_price) as list_price, MIN(currency) as currency, MAX(updated_at) as updated_at'
                    )
                    ->groupBy('product_id')
            );

            DB::table('dealer_price_overrides')->insertUsing(
                ['dealer_id', 'product_id', 'net_price', 'currency', 'updated_at'],
                DB::table('dealer_product_price')
                    ->select(['dealer_id', 'product_id', 'net_price', 'currency', 'updated_at'])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealer_price_overrides');
        Schema::dropIfExists('base_prices');

        Schema::table('dealers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_id');
        });

        Schema::dropIfExists('price_lists');
    }
};
