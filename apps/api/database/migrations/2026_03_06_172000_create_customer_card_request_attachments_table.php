<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_card_request_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_card_request_id');
            $table->foreign('customer_card_request_id', 'ccra_request_fk')
                ->references('id')
                ->on('customer_card_requests')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id');
            $table->foreign('uploaded_by_user_id', 'ccra_uploaded_by_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->string('attachment_type', 40);
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['customer_card_request_id', 'attachment_type'], 'customer_card_request_attachment_scope_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_card_request_attachments');
    }
};
