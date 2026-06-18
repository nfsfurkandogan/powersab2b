<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_card_requests', function (Blueprint $table): void {
            $table->foreignId('salesperson_user_id')->nullable()->after('dealer_id')->constrained('users')->nullOnDelete();
            $table->string('customer_kind', 24)->default('company')->after('email');
            $table->string('logo_special_code', 64)->default('F1')->after('customer_kind');
            $table->string('logo_authorization_code', 64)->nullable()->after('logo_special_code');
            $table->string('logo_e_collection_note', 160)->nullable()->after('logo_authorization_code');

            $table->index(['dealer_id', 'salesperson_user_id'], 'customer_card_requests_dealer_salesperson_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_card_requests', function (Blueprint $table): void {
            $table->dropIndex('customer_card_requests_dealer_salesperson_index');
            $table->dropConstrainedForeignId('salesperson_user_id');
            $table->dropColumn([
                'customer_kind',
                'logo_special_code',
                'logo_authorization_code',
                'logo_e_collection_note',
            ]);
        });
    }
};
