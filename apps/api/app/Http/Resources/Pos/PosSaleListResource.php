<?php

namespace App\Http\Resources\Pos;

use App\Models\IntegrationSyncState;
use App\Models\PosSale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSaleListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $logoSyncState = $this->logoSyncState();

        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'status' => $this->status,
            'sale_type' => $this->sale_type,
            'document_type' => $this->document_type,
            'subtotal' => number_format((float) $this->subtotal, 2, '.', ''),
            'discount_total' => number_format((float) $this->discount_total, 2, '.', ''),
            'vat_total' => number_format((float) $this->vat_total, 2, '.', ''),
            'grand_total' => number_format((float) $this->grand_total, 2, '.', ''),
            'customer' => [
                'id' => $this->customer?->id,
                'code' => $this->customer?->code,
                'title' => $this->customer?->name,
            ],
            'cashbox' => [
                'id' => $this->posSession?->cashbox?->id,
                'code' => $this->posSession?->cashbox?->code,
                'name' => $this->posSession?->cashbox?->name,
            ],
            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'logo_sync_status' => $logoSyncState?->status,
            'logo_sync_error' => $logoSyncState?->last_error,
            'logo_external_ref' => $logoSyncState?->external_ref,
            'logo_last_synced_at' => $logoSyncState?->last_synced_at,
            'created_at' => $this->created_at,
        ];
    }

    private function logoSyncState(): ?IntegrationSyncState
    {
        return IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'pos-sales')
            ->where('direction', 'outbound')
            ->where('entity_type', PosSale::class)
            ->where('entity_id', $this->id)
            ->latest('id')
            ->first();
    }
}
