<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_card_requests', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('dealer_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('converted_by_user_id')->nullable()->after('reviewed_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('reviewed_at');

            $table->index(['customer_id', 'converted_at'], 'customer_card_requests_customer_converted_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_card_requests', function (Blueprint $table): void {
            $table->dropIndex('customer_card_requests_customer_converted_index');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('converted_by_user_id');
            $table->dropColumn('converted_at');
        });
    }
};
