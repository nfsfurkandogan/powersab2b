<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained('dealers')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_no')->unique();
            $table->string('document_no')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('warehouse_code')->nullable();
            $table->string('warehouse_name')->nullable();
            $table->date('received_at');
            $table->text('note')->nullable();
            $table->enum('status', ['draft', 'queued', 'synced', 'failed', 'cancelled'])->default('queued');
            $table->timestamps();

            $table->index(['dealer_id', 'status', 'received_at']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('accepted_quantity')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['purchase_receipt_id', 'product_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
