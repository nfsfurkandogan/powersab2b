<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->date('date')->nullable()->after('customer_id');
            $table->json('reference_fields')->nullable()->after('reference_no');
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('collected_by_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['customer_id', 'date'], 'collections_customer_date_index');
            $table->index(['dealer_id', 'method', 'date'], 'collections_dealer_method_date_index');
        });

        DB::statement("
            UPDATE collections
            SET
                `date` = collection_date,
                created_by_user_id = collected_by_user_id,
                reference_fields = JSON_OBJECT(
                    'reference_no', reference_no,
                    'legacy_meta', meta
                )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_dealer_method_date_index');
            $table->dropIndex('collections_customer_date_index');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['date', 'reference_fields']);
        });
    }
};
