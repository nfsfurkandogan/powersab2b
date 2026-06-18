<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\IntegrationSyncEvent;
use App\Models\IntegrationSyncState;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\Logo\Contracts\LogoWriteTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogoWritePublisherApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_b2b_customer_queues_logo_customer_create_event(): void
    {
        config()->set('integrations.logo.write.enabled', false);

        $dealer = Dealer::query()->create([
            'code' => 'DLR-WRITE-'.Str::upper(Str::random(4)),
            'name' => 'Write Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('dealer_admin', $dealer);

        $this->actingAs($user);

        $response = $this->postJson('/api/customers', [
            'code' => 'CR-NEW-001',
            'name' => 'Yeni Cari',
            'email' => 'yeni@example.com',
            'phone' => '05550000000',
            'city' => 'Istanbul',
            'district' => 'Umraniye',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.source_system', 'b2b')
            ->assertJsonPath('data.sync_status', 'pending');

        $customer = Customer::query()->where('code', 'CR-NEW-001')->firstOrFail();

        $state = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'customers-write')
            ->where('direction', 'outbound')
            ->where('entity_type', Customer::class)
            ->where('entity_id', $customer->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame('queued', $state->status);
        $this->assertSame('logo.customer.create', data_get($state->meta, 'event_type'));

        $event = IntegrationSyncEvent::query()
            ->where('integration_sync_state_id', $state->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('queued', $event->status);
        $this->assertSame('logo.customer.create', data_get($event->meta, 'envelope.event_type'));
        $this->assertSame('CR-NEW-001', data_get($event->meta, 'envelope.payload.customer_code'));
    }

    public function test_updating_b2b_customer_queues_logo_customer_update_event(): void
    {
        config()->set('integrations.logo.write.enabled', false);

        $dealer = Dealer::query()->create([
            'code' => 'DLR-UPD-'.Str::upper(Str::random(4)),
            'name' => 'Update Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('dealer_admin', $dealer);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'b2b',
            'sync_status' => 'synced',
            'code' => 'CR-UPD-001',
            'name' => 'Eski Cari',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/customers/{$customer->id}", [
            'name' => 'Guncel Cari',
            'city' => 'Ankara',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Guncel Cari')
            ->assertJsonPath('data.sync_status', 'pending');

        $state = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'customers-write')
            ->where('direction', 'outbound')
            ->where('entity_type', Customer::class)
            ->where('entity_id', $customer->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame('logo.customer.update', data_get($state->meta, 'event_type'));

        $event = IntegrationSyncEvent::query()
            ->where('integration_sync_state_id', $state->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('logo.customer.update', data_get($event->meta, 'envelope.event_type'));
        $this->assertSame('Guncel Cari', data_get($event->meta, 'envelope.payload.name'));
        $this->assertSame('Ankara', data_get($event->meta, 'envelope.payload.city'));
    }

    public function test_updating_logo_sourced_customer_does_not_queue_write_event(): void
    {
        config()->set('integrations.logo.write.enabled', false);

        $dealer = Dealer::query()->create([
            'code' => 'DLR-LOGO-'.Str::upper(Str::random(4)),
            'name' => 'Logo Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('dealer_admin', $dealer);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'logo',
            'source_reference' => '1001',
            'sync_status' => 'synced',
            'code' => 'CR-LOGO-001',
            'name' => 'Logo Cari',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/customers/{$customer->id}", [
            'name' => 'Logo Cari Guncel',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Logo Cari Guncel')
            ->assertJsonPath('data.source_system', 'logo')
            ->assertJsonPath('data.sync_status', 'synced');

        $this->assertDatabaseMissing('integration_sync_states', [
            'system' => 'logo',
            'domain' => 'customers-write',
            'direction' => 'outbound',
            'entity_type' => Customer::class,
            'entity_id' => $customer->id,
        ]);
    }

    public function test_create_event_is_marked_published_when_transport_succeeds(): void
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
            'code' => 'DLR-PUB-'.Str::upper(Str::random(4)),
            'name' => 'Published Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('dealer_admin', $dealer);

        $this->actingAs($user);

        $this->postJson('/api/customers', [
            'code' => 'CR-PUB-001',
            'name' => 'Publish Cari',
        ])->assertCreated();

        $customer = Customer::query()->where('code', 'CR-PUB-001')->firstOrFail();

        $state = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'customers-write')
            ->where('entity_type', Customer::class)
            ->where('entity_id', $customer->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame('published', $state->status);

        $event = IntegrationSyncEvent::query()
            ->where('integration_sync_state_id', $state->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('published', $event->status);
        $this->assertCount(1, $recorder->items);
        $this->assertSame('logo.customer.create', $recorder->items[0]['event_type'] ?? null);
    }

    public function test_bridge_transport_keeps_write_event_queued_for_sync_bridge_polling(): void
    {
        config()->set('integrations.logo.write.enabled', true);
        config()->set('integrations.logo.write.transport', 'bridge');

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
            'code' => 'DLR-BRIDGE-'.Str::upper(Str::random(4)),
            'name' => 'Bridge Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('dealer_admin', $dealer);

        $this->actingAs($user);

        $this->postJson('/api/customers', [
            'code' => 'CR-BRIDGE-001',
            'name' => 'Bridge Cari',
        ])->assertCreated();

        $customer = Customer::query()->where('code', 'CR-BRIDGE-001')->firstOrFail();

        $state = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'customers-write')
            ->where('entity_type', Customer::class)
            ->where('entity_id', $customer->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame('queued', $state->status);
        $this->assertSame('bridge', data_get($state->meta, 'transport'));
        $this->assertCount(0, $recorder->items);
    }

    private function createUserWithRole(string $roleSlug, Dealer $dealer): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline(str_replace('_', ' ', $roleSlug))]
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'is_active' => true,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }
}
