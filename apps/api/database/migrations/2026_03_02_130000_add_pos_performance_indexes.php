<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->index(
                ['pos_session_id', 'status', 'created_at'],
                'pos_sales_session_status_created_index'
            );
            $table->index(
                ['created_by', 'status', 'created_at'],
                'pos_sales_creator_status_created_index'
            );
            $table->index(
                ['status', 'sale_type', 'created_at'],
                'pos_sales_status_type_created_index'
            );
        });

        Schema::table('pos_payments', function (Blueprint $table) {
            $table->index(
                ['method', 'pos_sale_id'],
                'pos_payments_method_sale_index'
            );
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->index(
                ['cashbox_id', 'opened_by', 'status'],
                'pos_sessions_cashbox_opened_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropIndex('pos_sessions_cashbox_opened_status_index');
        });

        Schema::table('pos_payments', function (Blueprint $table) {
            $table->dropIndex('pos_payments_method_sale_index');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropIndex('pos_sales_status_type_created_index');
            $table->dropIndex('pos_sales_creator_status_created_index');
            $table->dropIndex('pos_sales_session_status_created_index');
        });
    }
};
