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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('logo_cashbox_code', 64)
                ->nullable()
                ->after('logo_customer_specode4');
            $table->string('logo_cashbox_name', 128)
                ->nullable()
                ->after('logo_cashbox_code');

            $table->index(
                ['dealer_id', 'logo_cashbox_code'],
                'users_dealer_logo_cashbox_code_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_dealer_logo_cashbox_code_index');
            $table->dropColumn(['logo_cashbox_code', 'logo_cashbox_name']);
        });
    }
};
