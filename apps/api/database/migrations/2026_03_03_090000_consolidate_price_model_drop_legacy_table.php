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
        if (! Schema::hasTable('dealer_product_price')) {
            return;
        }

        if (! Schema::hasTable('dealer_product_price_backup')) {
            Schema::create('dealer_product_price_backup', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dealer_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('net_price', 15, 2);
                $table->char('currency', 3)->default('TRY');
                $table->timestampTz('updated_at')->nullable();
                $table->timestampTz('archived_at')->useCurrent();

                $table->unique(['dealer_id', 'product_id'], 'dealer_product_price_backup_unique');
                $table->index(['product_id', 'dealer_id'], 'dealer_product_price_backup_product_dealer_idx');
            });
        }

        $backupRows = DB::table('dealer_product_price')->get([
            'dealer_id',
            'product_id',
            'net_price',
            'currency',
            'updated_at',
        ]);

        foreach ($backupRows as $row) {
            DB::table('dealer_product_price_backup')->updateOrInsert(
                [
                    'dealer_id' => (int) $row->dealer_id,
                    'product_id' => (int) $row->product_id,
                ],
                [
                    'net_price' => (float) $row->net_price,
                    'currency' => (string) ($row->currency ?? 'TRY'),
                    'updated_at' => $row->updated_at ?? now(),
                    'archived_at' => now(),
                ]
            );
        }

        $defaultPriceListId = null;

        if (Schema::hasTable('price_lists')) {
            $defaultPriceListId = DB::table('price_lists')
                ->where('code', 'A')
                ->value('id');

            if ($defaultPriceListId === null) {
                $defaultPriceListId = DB::table('price_lists')
                    ->orderBy('id')
                    ->value('id');
            }
        }

        if ($defaultPriceListId !== null && Schema::hasTable('base_prices')) {
            $baseRows = DB::table('dealer_product_price')
                ->select('product_id')
                ->selectRaw('MIN(net_price) as list_price')
                ->selectRaw('MIN(currency) as currency')
                ->selectRaw('MAX(updated_at) as updated_at')
                ->groupBy('product_id')
                ->get();

            foreach ($baseRows as $row) {
                DB::table('base_prices')->updateOrInsert(
                    [
                        'price_list_id' => (int) $defaultPriceListId,
                        'product_id' => (int) $row->product_id,
                    ],
                    [
                        'list_price' => (float) $row->list_price,
                        'currency' => (string) ($row->currency ?? 'TRY'),
                        'updated_at' => $row->updated_at ?? now(),
                    ]
                );
            }
        }

        if (Schema::hasTable('dealer_price_overrides')) {
            $overrideRows = DB::table('dealer_product_price')->get([
                'dealer_id',
                'product_id',
                'net_price',
                'currency',
                'updated_at',
            ]);

            foreach ($overrideRows as $row) {
                DB::table('dealer_price_overrides')->updateOrInsert(
                    [
                        'dealer_id' => (int) $row->dealer_id,
                        'product_id' => (int) $row->product_id,
                    ],
                    [
                        'net_price' => (float) $row->net_price,
                        'currency' => (string) ($row->currency ?? 'TRY'),
                        'updated_at' => $row->updated_at ?? now(),
                    ]
                );
            }
        }

        Schema::dropIfExists('dealer_product_price');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('dealer_product_price')) {
            return;
        }

        Schema::create('dealer_product_price', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('net_price', 15, 2);
            $table->char('currency', 3)->default('TRY');
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['dealer_id', 'product_id']);
            $table->index(['product_id', 'dealer_id']);
        });

        if (Schema::hasTable('dealer_price_overrides')) {
            DB::table('dealer_product_price')->insertUsing(
                ['dealer_id', 'product_id', 'net_price', 'currency', 'updated_at'],
                DB::table('dealer_price_overrides')
                    ->select(['dealer_id', 'product_id', 'net_price', 'currency', 'updated_at'])
            );
        }

        if (Schema::hasTable('dealer_product_price_backup')) {
            $rows = DB::table('dealer_product_price_backup')->get([
                'dealer_id',
                'product_id',
                'net_price',
                'currency',
                'updated_at',
            ]);

            foreach ($rows as $row) {
                DB::table('dealer_product_price')->updateOrInsert(
                    [
                        'dealer_id' => (int) $row->dealer_id,
                        'product_id' => (int) $row->product_id,
                    ],
                    [
                        'net_price' => (float) $row->net_price,
                        'currency' => (string) ($row->currency ?? 'TRY'),
                        'updated_at' => $row->updated_at ?? now(),
                    ]
                );
            }
        }
    }
};
