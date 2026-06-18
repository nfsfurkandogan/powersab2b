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
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->date('date')->nullable()->after('customer_id');
            $table->enum('type', ['invoice', 'payment', 'credit', 'debit'])->nullable()->after('date');
            $table->decimal('debit', 15, 2)->nullable()->after('type');
            $table->decimal('credit', 15, 2)->nullable()->after('debit');
            $table->decimal('balance_after', 15, 2)->nullable()->after('credit');

            $table->index(['customer_id', 'date', 'id'], 'ledger_entries_customer_date_id_index');
            $table->index(['dealer_id', 'date', 'customer_id'], 'ledger_entries_dealer_date_customer_index');
            $table->index(['dealer_id', 'type', 'date'], 'ledger_entries_dealer_type_date_index');
        });

        DB::statement("
            UPDATE ledger_entries
            SET
                `date` = entry_date,
                `type` = CASE
                    WHEN order_id IS NOT NULL THEN 'invoice'
                    WHEN collection_id IS NOT NULL THEN 'payment'
                    WHEN entry_type = 'debit' THEN 'debit'
                    ELSE 'credit'
                END,
                debit = CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END,
                credit = CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END
        ");

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('
                UPDATE ledger_entries le
                JOIN (
                    SELECT
                        id,
                        SUM(COALESCE(debit, 0) - COALESCE(credit, 0))
                            OVER (PARTITION BY customer_id ORDER BY `date`, id) AS running_balance
                    FROM ledger_entries
                ) calc ON calc.id = le.id
                SET le.balance_after = calc.running_balance
            ');
        } else {
            DB::statement('
                WITH calc AS (
                    SELECT
                        id,
                        SUM(COALESCE(debit, 0) - COALESCE(credit, 0))
                            OVER (PARTITION BY customer_id ORDER BY date, id) AS running_balance
                    FROM ledger_entries
                )
                UPDATE ledger_entries
                SET balance_after = (
                    SELECT calc.running_balance
                    FROM calc
                    WHERE calc.id = ledger_entries.id
                )
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex('ledger_entries_dealer_type_date_index');
            $table->dropIndex('ledger_entries_dealer_date_customer_index');
            $table->dropIndex('ledger_entries_customer_date_id_index');
            $table->dropColumn(['date', 'type', 'debit', 'credit', 'balance_after']);
        });
    }
};
