<?php

namespace App\Support\Cart;

use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Support\Carbon;

class CartLogoIntegrationSummary
{
    /**
     * @return array<string, mixed>
     */
    public function forCart(Cart $cart): array
    {
        $customer = $cart->customer;
        $itemsTotal = $cart->items->count();
        $itemsReady = 0;
        $latestProductSyncedAt = null;

        foreach ($cart->items as $item) {
            $logoMeta = data_get($item->product?->meta, 'integrations.logo', []);
            $externalRef = $this->nullableString(data_get($logoMeta, 'external_ref'));

            if ($externalRef !== null) {
                $itemsReady++;
            }

            $syncedAt = $this->nullableString(data_get($logoMeta, 'synced_at'));
            if ($syncedAt !== null && ($latestProductSyncedAt === null || strcmp($syncedAt, $latestProductSyncedAt) > 0)) {
                $latestProductSyncedAt = $syncedAt;
            }
        }

        $customerReady = $this->isCustomerLogoReady($customer);
        $itemsMissing = max(0, $itemsTotal - $itemsReady);

        return [
            'customer_ready' => $customerReady,
            'customer_source_system' => $customer?->source_system,
            'customer_external_ref' => $customer?->source_reference,
            'customer_last_synced_at' => $customer?->last_synced_at instanceof Carbon
                ? $customer->last_synced_at->toJSON()
                : $customer?->last_synced_at,
            'items_total' => $itemsTotal,
            'items_ready' => $itemsReady,
            'items_missing' => $itemsMissing,
            'latest_product_synced_at' => $latestProductSyncedAt,
            'order_will_queue' => $itemsTotal > 0 && $customerReady && $itemsMissing === 0,
        ];
    }

    private function isCustomerLogoReady(?Customer $customer): bool
    {
        if (! $customer instanceof Customer) {
            return false;
        }

        if ($this->nullableString($customer->source_reference) !== null) {
            return true;
        }

        if ($customer->source_system === 'logo') {
            return true;
        }

        return $customer->source_system === 'b2b' && $customer->sync_status === 'synced';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
