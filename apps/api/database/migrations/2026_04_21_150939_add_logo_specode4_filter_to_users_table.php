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
            $table->string('logo_customer_specode4', 64)
                ->nullable()
                ->after('branch_name');

            $table->index(
                ['dealer_id', 'logo_customer_specode4'],
                'users_dealer_logo_customer_specode4_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_dealer_logo_customer_specode4_index');
            $table->dropColumn('logo_customer_specode4');
        });
    }
};
