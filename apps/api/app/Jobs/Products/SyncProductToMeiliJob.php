<?php

namespace App\Jobs\Products;

use App\Services\Meilisearch\ProductSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductToMeiliJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  'upsert'|'delete'  $action
     */
    public function __construct(
        public int $productId,
        public string $action = 'upsert'
    ) {
        $this->onQueue((string) config('meilisearch.queue', 'default'));
        $this->afterCommit();
    }

    public function handle(ProductSearchService $service): void
    {
        if (! $service->isEnabled()) {
            return;
        }

        if ($this->action === 'delete') {
            $service->deleteProductById($this->productId);

            return;
        }

        $service->upsertProductById($this->productId);
    }
}
