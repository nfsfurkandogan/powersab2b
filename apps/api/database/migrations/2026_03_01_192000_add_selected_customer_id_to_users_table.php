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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('selected_customer_id')
                ->nullable()
                ->after('dealer_id')
                ->constrained('customers')
                ->nullOnDelete();

            $table->index(['dealer_id', 'selected_customer_id'], 'users_dealer_selected_customer_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_dealer_selected_customer_index');
            $table->dropConstrainedForeignId('selected_customer_id');
        });
    }
};
