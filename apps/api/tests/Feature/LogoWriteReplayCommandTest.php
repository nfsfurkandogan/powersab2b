<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\IntegrationSyncEvent;
use App\Models\IntegrationSyncState;
use App\Services\Integrations\Logo\Contracts\LogoWriteTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogoWriteReplayCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_replay_command_publishes_queued_write_events(): void
    {
        config()->set('integrations.logo.write.enabled', true);
        config()->set('integrations.logo.write.transport', 'rabbitmq');

        $recorder = new class
        {
            /**
             * @var array<int, array<string, mixed>>
             */
            public array $items = [];
        };

        $this->app->instance(LogoWriteTransport::class, new class($recorder) implements LogoWriteTransport
        {
            public function __construct(private object $recorder) {}

            public function publish(array $envelope): void
            {
                $this->recorder->items[] = $envelope;
            }
        });

        $dealer = Dealer::query()->create([
            'code' => 'DLR-RPL-'.Str::upper(Str::random(4)),
            'name' => 'Replay Dealer',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'b2b',
            'sync_status' => 'pending',
            'code' => 'CR-RPL-001',
            'name' => 'Replay Cari',
            'is_active' => true,
        ]);

        $state = IntegrationSyncState::query()->create([
            'system' => 'logo',
            'domain' => 'customers-write',
            'direction' => 'outbound',
            'entity_type' => Customer::class,
            'entity_id' => $customer->id,
            'dealer_id' => $dealer->id,
            'status' => 'queued',
            'meta' => [
                'queue_status' => 'queued',
            ],
        ]);

        $event = IntegrationSyncEvent::query()->create([
            'integration_sync_state_id' => $state->id,
            'system' => 'logo',
            'domain' => 'customers-write',
            'direction' => 'outbound',
            'entity_type' => Customer::class,
            'entity_id' => $customer->id,
            'dealer_id' => $dealer->id,
            'status' => 'queued',
            'meta' => [
                'envelope' => [
                    'event_id' => 'evt-1',
                    'event_type' => 'logo.customer.create',
                    'routing_key' => 'logo.customer.create',
                    'exchange' => 'powersa.logo',
                    'occurred_at' => now()->toIso8601String(),
                    'dealer_id' => $dealer->id,
                    'entity_type' => 'customer',
                    'entity_id' => $customer->id,
                    'idempotency_key' => 'B2B-CUST-CREATE-'.$customer->id,
                    'payload' => [
                        'customer_id' => $customer->id,
                        'customer_code' => $customer->code,
                        'name' => $customer->name,
                    ],
                ],
            ],
            'happened_at' => now(),
        ]);

        $this->artisan('logo:writes-replay')
            ->expectsTable(
                ['Scanned', 'Published', 'Failed', 'Skipped'],
                [['1', '1', '0', '0']]
            )
            ->assertSuccessful();

        $this->assertCount(1, $recorder->items);
        $this->assertSame('logo.customer.create', $recorder->items[0]['event_type'] ?? null);

        $this->assertSame('published', $event->fresh()->status);
        $this->assertSame('published', $state->fresh()->status);
    }
}
