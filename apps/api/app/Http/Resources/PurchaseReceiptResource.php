<?php

namespace App\Http\Resources;

use App\Models\IntegrationSyncState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $syncState = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'purchase-receipts')
            ->where('direction', 'outbound')
            ->where('entity_type', $this->resource::class)
            ->where('entity_id', (int) $this->id)
            ->first();

        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'document_no' => $this->document_no,
            'supplier_name' => $this->supplier_name,
            'warehouse_code' => $this->warehouse_code,
            'warehouse_name' => $this->warehouse_name,
            'received_at' => optional($this->received_at)?->toDateString(),
            'note' => $this->note,
            'status' => $this->status,
            'logo_sync_status' => $syncState?->status,
            'logo_sync_error' => $syncState?->last_error,
            'logo_external_ref' => $syncState?->external_ref,
            'logo_last_synced_at' => $syncState?->last_synced_at,
            'items' => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_code' => $item->product_code,
                'product_name' => $item->product_name,
                'expected_quantity' => (int) $item->expected_quantity,
                'accepted_quantity' => (int) $item->accepted_quantity,
                'note' => $item->note,
            ])->values()->all(),
        ];
    }
}
