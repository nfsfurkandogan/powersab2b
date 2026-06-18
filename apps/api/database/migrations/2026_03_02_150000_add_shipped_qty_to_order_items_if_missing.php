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
        if (! Schema::hasTable('order_items') || Schema::hasColumn('order_items', 'shipped_qty')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('shipped_qty')->default(0)->after('quantity');
            $table->index(['order_id', 'shipped_qty'], 'order_items_order_shipped_qty_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasColumn('order_items', 'shipped_qty')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_shipped_qty_index');
            $table->dropColumn('shipped_qty');
        });
    }
};
