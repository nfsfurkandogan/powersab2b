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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('shipment_no')->unique();
            $table->enum('status', ['draft', 'picking', 'packed', 'shipped', 'partially_shipped', 'cancelled'])
                ->default('draft');
            $table->string('carrier_name')->nullable();
            $table->string('tracking_no')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('shipped_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status', 'shipped_at']);
            $table->index(['warehouse_id', 'status', 'shipped_at']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('ordered_qty');
            $table->unsignedInteger('shipped_qty')->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_total_shipped', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['shipment_id', 'order_item_id'], 'shipment_items_shipment_order_item_unique');
            $table->index(['shipment_id', 'product_id']);
            $table->index('order_item_id');
        });

        Schema::create('shipment_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('barcode');
            $table->unsignedInteger('qty')->default(1);
            $table->foreignId('scanned_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('scanned_at')->useCurrent();

            $table->index(['shipment_id', 'product_id', 'scanned_at']);
            $table->index(['barcode', 'scanned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_scans');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('warehouses');
    }
};
