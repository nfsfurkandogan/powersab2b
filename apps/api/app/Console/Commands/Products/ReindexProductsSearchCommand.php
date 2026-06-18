<?php

namespace App\Console\Commands\Products;

use App\Jobs\Products\ReindexProductsToMeiliJob;
use App\Services\Meilisearch\ProductSearchService;
use Illuminate\Console\Command;

class ReindexProductsSearchCommand extends Command
{
    protected $signature = 'products:search-reindex {--sync : Run immediately instead of queue job} {--chunk=500 : Chunk size for bulk indexing}';

    protected $description = 'Reindex products into Meilisearch';

    public function handle(ProductSearchService $service): int
    {
        $chunk = (int) $this->option('chunk');

        if ($this->option('sync')) {
            $service->reindexAll($chunk);
            $this->info('Products reindexed to Meilisearch synchronously.');

            return self::SUCCESS;
        }

        ReindexProductsToMeiliJob::dispatch($chunk);
        $this->info('Reindex job dispatched.');

        return self::SUCCESS;
    }
}
