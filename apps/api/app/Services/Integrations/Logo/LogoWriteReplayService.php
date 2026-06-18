<?php

namespace App\Services\Integrations\Logo;

use App\Models\IntegrationSyncEvent;
use App\Models\IntegrationSyncState;
use App\Services\Integrations\Logo\Contracts\LogoWriteTransport;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class LogoWriteReplayService
{
    public function __construct(
        private readonly LogoWriteTransport $transport
    ) {}

    /**
     * @return array<string, int>
     */
    public function replay(bool $force = false, ?string $domain = null, int $limit = 100): array
    {
        if (! $this->canPublish($force)) {
            return [
                'scanned' => 0,
                'published' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        $events = $this->pendingEvents($domain, $limit);

        $summary = [
            'scanned' => $events->count(),
            'published' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($events as $event) {
            $envelope = data_get($event->meta, 'envelope');

            if (! is_array($envelope) || $envelope === []) {
                $summary['skipped']++;

                continue;
            }

            try {
                $this->transport->publish($envelope);
                $this->markPublished($event);
                $summary['published']++;
            } catch (Throwable $exception) {
                $this->markFailed($event, $exception->getMessage());
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @return Collection<int, IntegrationSyncEvent>
     */
    private function pendingEvents(?string $domain, int $limit): Collection
    {
        $query = IntegrationSyncEvent::query()
            ->with('state')
            ->where('system', 'logo')
            ->where('direction', 'outbound')
            ->whereIn('domain', ['customers-write', 'collections-write'])
            ->whereIn('status', ['queued', 'publish_failed'])
            ->orderBy('id');

        if ($domain !== null && $domain !== '') {
            $query->where('domain', $domain);
        }

        return $query->limit(max(1, min($limit, 1000)))->get();
    }

    private function markPublished(IntegrationSyncEvent $event): void
    {
        $timestamp = now();

        $event->forceFill([
            'status' => 'published',
            'error' => null,
            'meta' => array_replace_recursive(
                is_array($event->meta) ? $event->meta : [],
                [
                    'published' => true,
                    'published_at' => $timestamp->toIso8601String(),
                    'replayed' => true,
                ]
            ),
        ])->save();

        if ($event->state instanceof IntegrationSyncState) {
            $event->state->forceFill([
                'status' => 'published',
                'last_error' => null,
                'meta' => array_replace_recursive(
                    is_array($event->state->meta) ? $event->state->meta : [],
                    [
                        'queue_status' => 'published',
                        'published_at' => $timestamp->toIso8601String(),
                        'replayed' => true,
                    ]
                ),
            ])->save();
        }
    }

    private function markFailed(IntegrationSyncEvent $event, string $error): void
    {
        $timestamp = now();

        $event->forceFill([
            'status' => 'publish_failed',
            'error' => $error,
            'meta' => array_replace_recursive(
                is_array($event->meta) ? $event->meta : [],
                [
                    'published' => false,
                    'publish_failed_at' => $timestamp->toIso8601String(),
                    'replayed' => true,
                ]
            ),
        ])->save();

        if ($event->state instanceof IntegrationSyncState) {
            $event->state->forceFill([
                'status' => 'publish_failed',
                'last_error' => $error,
                'meta' => array_replace_recursive(
                    is_array($event->state->meta) ? $event->state->meta : [],
                    [
                        'queue_status' => 'publish_failed',
                        'publish_failed_at' => $timestamp->toIso8601String(),
                        'replayed' => true,
                    ]
                ),
            ])->save();
        }
    }

    private function canPublish(bool $force): bool
    {
        return ((bool) config('integrations.logo.write.enabled', false) || $force)
            && (string) config('integrations.logo.write.transport', 'bridge') === 'rabbitmq';
    }
}
