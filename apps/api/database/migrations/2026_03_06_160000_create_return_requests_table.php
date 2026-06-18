<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_no', 32)->unique();
            $table->string('request_type', 32);
            $table->string('status', 32)->default('submitted');
            $table->string('reason_code', 64);
            $table->text('reason_note')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->string('currency', 3)->default('TRY');
            $table->json('product_snapshot')->nullable();
            $table->json('order_snapshot')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'status', 'created_at']);
            $table->index(['customer_id', 'status', 'created_at']);
            $table->index(['order_id', 'order_item_id']);
            $table->index(['requested_by_user_id', 'created_at']);
            $table->index(['request_type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
