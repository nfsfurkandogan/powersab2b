<?php

namespace App\Jobs\Products;

use App\Services\Meilisearch\ProductSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReindexProductsToMeiliJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public ?int $chunkSize = null)
    {
        $this->onQueue((string) config('meilisearch.queue', 'default'));
    }

    public function handle(ProductSearchService $service): void
    {
        if (! $service->isEnabled()) {
            return;
        }

        $service->reindexAll($this->chunkSize);
    }
}
