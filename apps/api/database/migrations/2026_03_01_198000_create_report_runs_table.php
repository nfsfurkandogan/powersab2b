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
        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 80);
            $table->string('status', 24)->default('queued');
            $table->json('parameters')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'report_runs_user_status_created_index');
            $table->index(['report_key', 'status', 'created_at'], 'report_runs_key_status_created_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['dealer_id', 'ordered_at', 'id'], 'orders_dealer_ordered_id_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['product_id', 'order_id', 'line_total'], 'order_items_product_order_total_index');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->index(['dealer_id', 'date', 'customer_id'], 'collections_dealer_date_customer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_dealer_date_customer_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_product_order_total_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_dealer_ordered_id_index');
        });

        Schema::dropIfExists('report_runs');
    }
};
