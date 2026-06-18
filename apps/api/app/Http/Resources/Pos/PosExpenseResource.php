<?php

namespace App\Http\Resources\Pos;

use App\Models\IntegrationSyncState;
use App\Models\PosExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sourceSystem = $this->sourceSystem();
        $logoSyncState = $this->logoSyncState();

        return [
            'id' => $this->id,
            'pos_session_id' => $this->pos_session_id,
            'expense_date' => optional($this->expense_date)->toDateString(),
            'category' => $this->category,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'currency' => $this->currency,
            'note' => $this->note,
            'source_system' => $sourceSystem,
            'source_label' => $sourceSystem === 'logo' ? 'Logo kaydı' : 'B2B kaydı',
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

    private function sourceSystem(): string
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $source = data_get($meta, 'source_system') ?? data_get($meta, 'source');

        return $source === 'logo' ? 'logo' : 'b2b';
    }

    private function logoSyncState(): ?IntegrationSyncState
    {
        $direction = $this->sourceSystem() === 'logo' ? 'inbound' : 'outbound';

        return IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'pos-expenses')
            ->where('direction', $direction)
            ->where('entity_type', PosExpense::class)
            ->where('entity_id', $this->id)
            ->latest('id')
            ->first();
    }
}
