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
            $table->string('customer_scope', 16)->default('dealer')->after('dealer_id');
            $table->string('region_code', 64)->nullable()->after('customer_scope');
            $table->string('region_name', 120)->nullable()->after('region_code');
            $table->string('branch_code', 64)->nullable()->after('region_name');
            $table->string('branch_name', 120)->nullable()->after('branch_code');

            $table->index(['dealer_id', 'customer_scope'], 'users_dealer_customer_scope_index');
            $table->index(['dealer_id', 'region_code'], 'users_dealer_region_code_index');
            $table->index(['dealer_id', 'branch_code'], 'users_dealer_branch_code_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('region_code', 64)->nullable()->after('salesperson_user_id');
            $table->string('region_name', 120)->nullable()->after('region_code');
            $table->string('branch_code', 64)->nullable()->after('region_name');
            $table->string('branch_name', 120)->nullable()->after('branch_code');

            $table->index(['dealer_id', 'region_code'], 'customers_dealer_region_code_index');
            $table->index(['dealer_id', 'branch_code'], 'customers_dealer_branch_code_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_dealer_region_code_index');
            $table->dropIndex('customers_dealer_branch_code_index');
            $table->dropColumn([
                'region_code',
                'region_name',
                'branch_code',
                'branch_name',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_dealer_customer_scope_index');
            $table->dropIndex('users_dealer_region_code_index');
            $table->dropIndex('users_dealer_branch_code_index');
            $table->dropColumn([
                'customer_scope',
                'region_code',
                'region_name',
                'branch_code',
                'branch_name',
            ]);
        });
    }
};
