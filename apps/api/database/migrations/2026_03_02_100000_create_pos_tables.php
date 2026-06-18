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
        Schema::create('cashboxes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashbox_id')->constrained('cashboxes')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('opened_at');
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->timestampTz('closed_at')->nullable();
            $table->decimal('closing_cash_counted', 15, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['cashbox_id', 'status', 'opened_at']);
            $table->index(['opened_by', 'status']);
            $table->index('closed_at');
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->enum('sale_type', ['cash', 'card', 'transfer']);
            $table->enum('document_type', ['invoice', 'delivery']);
            $table->string('receipt_no')->unique();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->enum('status', ['paid', 'cancelled'])->default('paid');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['pos_session_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['sale_type', 'document_type', 'created_at']);
        });

        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->index(['pos_sale_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained('pos_sales')->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['pos_sale_id', 'method']);
            $table->index('method');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->enum('source', ['pos_sale', 'order', 'shipment']);
            $table->unsignedBigInteger('source_id');
            $table->decimal('qty', 12, 3);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['source', 'source_id']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('cashboxes');
    }
};
