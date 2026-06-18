<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('category', 80);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('TRY');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['pos_session_id', 'expense_date'], 'pos_expenses_session_date_index');
            $table->index(['dealer_id', 'expense_date'], 'pos_expenses_dealer_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_expenses');
    }
};
