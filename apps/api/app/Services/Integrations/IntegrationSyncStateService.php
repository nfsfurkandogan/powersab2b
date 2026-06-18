<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSyncEvent;
use App\Models\IntegrationSyncState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class IntegrationSyncStateService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $system,
        string $domain,
        string $direction,
        Model $entity,
        ?string $externalRef,
        ?string $status,
        ?string $error = null,
        array $meta = [],
        mixed $payload = null,
        ?Carbon $syncedAt = null,
    ): IntegrationSyncState {
        $timestamp = $syncedAt ?? now();
        $payloadHash = $this->hashPayload($payload ?? $meta);

        $state = IntegrationSyncState::query()->firstOrNew([
            'system' => $system,
            'domain' => $domain,
            'direction' => $direction,
            'entity_type' => $entity::class,
            'entity_id' => (int) $entity->getKey(),
        ]);

        $existingMeta = is_array($state->meta) ? $state->meta : [];

        $state->fill([
            'dealer_id' => $this->resolveIntegerAttribute($entity, 'dealer_id'),
            'customer_id' => $this->resolveIntegerAttribute($entity, 'customer_id'),
            'external_ref' => $externalRef,
            'status' => $status,
            'last_error' => $error,
            'payload_hash' => $payloadHash,
            'last_synced_at' => $timestamp,
            'meta' => array_replace_recursive($existingMeta, $meta),
        ]);
        $state->save();

        IntegrationSyncEvent::query()->create([
            'integration_sync_state_id' => $state->id,
            'system' => $system,
            'domain' => $domain,
            'direction' => $direction,
            'entity_type' => $entity::class,
            'entity_id' => (int) $entity->getKey(),
            'dealer_id' => $this->resolveIntegerAttribute($entity, 'dealer_id'),
            'customer_id' => $this->resolveIntegerAttribute($entity, 'customer_id'),
            'external_ref' => $externalRef,
            'status' => $status,
            'error' => $error,
            'payload_hash' => $payloadHash,
            'meta' => $meta,
            'happened_at' => $timestamp,
        ]);

        return $state;
    }

    private function resolveIntegerAttribute(Model $entity, string $key): ?int
    {
        $value = $entity->getAttribute($key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function hashPayload(mixed $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return null;
        }

        return hash('sha256', $encoded);
    }
}
