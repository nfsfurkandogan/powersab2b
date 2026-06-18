<?php

namespace App\Services\Integrations\Logo;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\IntegrationSyncEvent;
use App\Models\IntegrationSyncState;
use App\Services\Integrations\Logo\Contracts\LogoWriteTransport;
use Throwable;

class LogoWritePublisher
{
    public function __construct(
        private readonly LogoWriteEventFactory $factory,
        private readonly LogoWriteTransport $transport,
    ) {}

    public function queueCustomerCreate(Customer $customer): IntegrationSyncEvent
    {
        return $this->persistCustomerEnvelope($customer, $this->factory->customerCreate($customer));
    }

    public function queueCustomerUpdate(Customer $customer): IntegrationSyncEvent
    {
        return $this->persistCustomerEnvelope($customer, $this->factory->customerUpdate($customer));
    }

    public function queueCollectionCreate(Collection $collection): IntegrationSyncEvent
    {
        return $this->persistCollectionEnvelope($collection, $this->factory->collectionCreate($collection));
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function persistCustomerEnvelope(Customer $customer, array $envelope): IntegrationSyncEvent
    {
        $timestamp = now();
        $payload = $envelope['payload'] ?? [];
        $payloadHash = $this->hashPayload($payload);

        $state = IntegrationSyncState::query()->firstOrNew([
            'system' => 'logo',
            'domain' => 'customers-write',
            'direction' => 'outbound',
            'entity_type' => $customer::class,
            'entity_id' => (int) $customer->getKey(),
        ]);

        $existingMeta = is_array($state->meta) ? $state->meta : [];

        $state->fill([
            'dealer_id' => $customer->dealer_id,
            'customer_id' => null,
            'external_ref' => $customer->source_reference,
            'status' => 'queued',
            'last_error' => null,
            'payload_hash' => $payloadHash,
            'meta' => array_replace_recursive($existingMeta, [
                'transport' => $this->writeTransport(),
                'exchange' => $envelope['exchange'] ?? null,
                'routing_key' => $envelope['routing_key'] ?? null,
                'event_type' => $envelope['event_type'] ?? null,
                'last_event_id' => $envelope['event_id'] ?? null,
                'last_idempotency_key' => $envelope['idempotency_key'] ?? null,
                'queue_status' => 'queued',
                'queued_at' => $timestamp->toIso8601String(),
            ]),
        ]);
        $state->save();

        $event = IntegrationSyncEvent::query()->create([
            'integration_sync_state_id' => $state->id,
            'system' => 'logo',
            'domain' => 'customers-write',
            'direction' => 'outbound',
            'entity_type' => $customer::class,
            'entity_id' => (int) $customer->getKey(),
            'dealer_id' => $customer->dealer_id,
            'customer_id' => null,
            'external_ref' => $customer->source_reference,
            'status' => 'queued',
            'error' => null,
            'payload_hash' => $payloadHash,
            'meta' => [
                'transport' => $this->writeTransport(),
                'envelope' => $envelope,
            ],
            'happened_at' => $timestamp,
        ]);

        if (! $this->shouldPublishImmediately()) {
            return $event;
        }

        try {
            $this->transport->publish($envelope);

            $state->forceFill([
                'status' => 'published',
                'last_error' => null,
                'meta' => array_replace_recursive(
                    is_array($state->meta) ? $state->meta : [],
                    [
                        'queue_status' => 'published',
                        'published_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();

            $event->forceFill([
                'status' => 'published',
                'error' => null,
                'meta' => array_replace_recursive(
                    is_array($event->meta) ? $event->meta : [],
                    [
                        'published' => true,
                        'published_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();
        } catch (Throwable $exception) {
            $state->forceFill([
                'status' => 'publish_failed',
                'last_error' => $exception->getMessage(),
                'meta' => array_replace_recursive(
                    is_array($state->meta) ? $state->meta : [],
                    [
                        'queue_status' => 'publish_failed',
                        'publish_failed_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();

            $event->forceFill([
                'status' => 'publish_failed',
                'error' => $exception->getMessage(),
                'meta' => array_replace_recursive(
                    is_array($event->meta) ? $event->meta : [],
                    [
                        'published' => false,
                        'publish_failed_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function persistCollectionEnvelope(Collection $collection, array $envelope): IntegrationSyncEvent
    {
        $timestamp = now();
        $payload = $envelope['payload'] ?? [];
        $payloadHash = $this->hashPayload($payload);

        $state = IntegrationSyncState::query()->firstOrNew([
            'system' => 'logo',
            'domain' => 'collections-write',
            'direction' => 'outbound',
            'entity_type' => $collection::class,
            'entity_id' => (int) $collection->getKey(),
        ]);

        $existingMeta = is_array($state->meta) ? $state->meta : [];

        $state->fill([
            'dealer_id' => $collection->dealer_id,
            'customer_id' => $collection->customer_id,
            'external_ref' => $collection->source_reference,
            'status' => 'queued',
            'last_error' => null,
            'payload_hash' => $payloadHash,
            'meta' => array_replace_recursive($existingMeta, [
                'transport' => $this->writeTransport(),
                'exchange' => $envelope['exchange'] ?? null,
                'routing_key' => $envelope['routing_key'] ?? null,
                'event_type' => $envelope['event_type'] ?? null,
                'last_event_id' => $envelope['event_id'] ?? null,
                'last_idempotency_key' => $envelope['idempotency_key'] ?? null,
                'queue_status' => 'queued',
                'queued_at' => $timestamp->toIso8601String(),
            ]),
        ]);
        $state->save();

        $event = IntegrationSyncEvent::query()->create([
            'integration_sync_state_id' => $state->id,
            'system' => 'logo',
            'domain' => 'collections-write',
            'direction' => 'outbound',
            'entity_type' => $collection::class,
            'entity_id' => (int) $collection->getKey(),
            'dealer_id' => $collection->dealer_id,
            'customer_id' => $collection->customer_id,
            'external_ref' => $collection->source_reference,
            'status' => 'queued',
            'error' => null,
            'payload_hash' => $payloadHash,
            'meta' => [
                'transport' => $this->writeTransport(),
                'envelope' => $envelope,
            ],
            'happened_at' => $timestamp,
        ]);

        if (! $this->shouldPublishImmediately()) {
            return $event;
        }

        try {
            $this->transport->publish($envelope);

            $state->forceFill([
                'status' => 'published',
                'last_error' => null,
                'meta' => array_replace_recursive(
                    is_array($state->meta) ? $state->meta : [],
                    [
                        'queue_status' => 'published',
                        'published_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();

            $event->forceFill([
                'status' => 'published',
                'error' => null,
                'meta' => array_replace_recursive(
                    is_array($event->meta) ? $event->meta : [],
                    [
                        'published' => true,
                        'published_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();
        } catch (Throwable $exception) {
            $state->forceFill([
                'status' => 'publish_failed',
                'last_error' => $exception->getMessage(),
                'meta' => array_replace_recursive(
                    is_array($state->meta) ? $state->meta : [],
                    [
                        'queue_status' => 'publish_failed',
                        'publish_failed_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();

            $event->forceFill([
                'status' => 'publish_failed',
                'error' => $exception->getMessage(),
                'meta' => array_replace_recursive(
                    is_array($event->meta) ? $event->meta : [],
                    [
                        'published' => false,
                        'publish_failed_at' => now()->toIso8601String(),
                    ]
                ),
            ])->save();
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        return hash(
            'sha256',
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function shouldPublishImmediately(): bool
    {
        return (bool) config('integrations.logo.write.enabled', false)
            && $this->writeTransport() === 'rabbitmq';
    }

    private function writeTransport(): string
    {
        return (string) config('integrations.logo.write.transport', 'bridge');
    }
}
