<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sync_states', function (Blueprint $table): void {
            $table->id();
            $table->string('system', 32);
            $table->string('domain', 64);
            $table->string('direction', 16);
            $table->string('entity_type', 191);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('dealer_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('external_ref', 128)->nullable();
            $table->string('status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['system', 'domain', 'direction', 'entity_type', 'entity_id'],
                'integration_sync_states_entity_unique'
            );
            $table->index(['system', 'domain', 'external_ref'], 'integration_sync_states_external_index');
            $table->index(['dealer_id', 'system', 'domain'], 'integration_sync_states_dealer_domain_index');
            $table->index(['customer_id', 'system', 'domain'], 'integration_sync_states_customer_domain_index');
        });

        Schema::create('integration_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_sync_state_id')
                ->nullable()
                ->constrained('integration_sync_states')
                ->nullOnDelete();
            $table->string('system', 32);
            $table->string('domain', 64);
            $table->string('direction', 16);
            $table->string('entity_type', 191);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('dealer_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('external_ref', 128)->nullable();
            $table->string('status', 32)->nullable();
            $table->text('error')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['system', 'domain', 'direction'], 'integration_sync_events_domain_index');
            $table->index(['entity_type', 'entity_id'], 'integration_sync_events_entity_index');
            $table->index(['external_ref'], 'integration_sync_events_external_ref_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_events');
        Schema::dropIfExists('integration_sync_states');
    }
};
