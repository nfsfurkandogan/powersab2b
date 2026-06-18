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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('salesperson_user_id')
                ->nullable()
                ->after('dealer_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['dealer_id', 'salesperson_user_id'],
                'customers_dealer_salesperson_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_dealer_salesperson_index');
            $table->dropConstrainedForeignId('salesperson_user_id');
        });
    }
};
