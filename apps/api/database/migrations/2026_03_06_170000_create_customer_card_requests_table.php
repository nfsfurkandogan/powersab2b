<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_card_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dealer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_no')->unique();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->string('city', 120);
            $table->string('district', 120)->nullable();
            $table->string('tax_office', 120)->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 32)->default('submitted');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['dealer_id', 'status', 'created_at'], 'customer_card_requests_scope_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_card_requests');
    }
};
