<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $date = $this->date ?? $this->entry_date;
        $type = $this->type ?? $this->entry_type;
        $debit = $this->debit ?? ($this->entry_type === 'debit' ? $this->amount : 0);
        $credit = $this->credit ?? ($this->entry_type === 'credit' ? $this->amount : 0);

        return [
            'id' => $this->id,
            'dealer_id' => $this->dealer_id,
            'customer_id' => $this->customer_id,
            'source_system' => $this->source_system,
            'source_reference' => $this->source_reference,
            'last_synced_at' => $this->last_synced_at,
            'date' => $date,
            'type' => $type,
            'debit' => number_format((float) $debit, 2, '.', ''),
            'credit' => number_format((float) $credit, 2, '.', ''),
            'balance_after' => number_format(
                (float) ($this->balance_after ?? ((float) $debit - (float) $credit)),
                2,
                '.',
                ''
            ),
            'description' => $this->description,
            'currency' => $this->currency,
            'reference_no' => $this->reference_no,
            'order_id' => $this->order_id,
            'collection_id' => $this->collection_id,
            'checkout_summary' => $this->checkoutSummary(),
            'entry_date' => $this->entry_date,
            'entry_type' => $this->entry_type,
            'amount' => $this->amount,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{mode: string, code: string, label: string}|null
     */
    private function checkoutSummary(): ?array
    {
        $summary = data_get($this->meta, 'checkout_summary');

        if (! is_array($summary)) {
            return null;
        }

        $mode = (string) ($summary['mode'] ?? '');
        $code = (string) ($summary['code'] ?? '');
        $label = (string) ($summary['label'] ?? '');

        if ($mode === '' || $code === '' || $label === '') {
            return null;
        }

        return [
            'mode' => $mode,
            'code' => $code,
            'label' => $label,
        ];
    }
}
