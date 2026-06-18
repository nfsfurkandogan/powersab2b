<?php

namespace App\Observers;

use App\Jobs\Products\SyncProductToMeiliJob;
use App\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        SyncProductToMeiliJob::dispatch($product->id, 'upsert');
    }

    public function updated(Product $product): void
    {
        SyncProductToMeiliJob::dispatch($product->id, 'upsert');
    }

    public function deleted(Product $product): void
    {
        SyncProductToMeiliJob::dispatch($product->id, 'delete');
    }

    public function restored(Product $product): void
    {
        SyncProductToMeiliJob::dispatch($product->id, 'upsert');
    }

    public function forceDeleted(Product $product): void
    {
        SyncProductToMeiliJob::dispatch($product->id, 'delete');
    }
}
