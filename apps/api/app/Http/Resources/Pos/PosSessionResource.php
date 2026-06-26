<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'opened_at' => $this->opened_at,
            'opening_cash' => number_format((float) $this->opening_cash, 2, '.', ''),
            'closed_at' => $this->closed_at,
            'closing_cash_counted' => $this->closing_cash_counted !== null
                ? number_format((float) $this->closing_cash_counted, 2, '.', '')
                : null,
            'cashbox' => [
                'id' => $this->cashbox?->id,
                'code' => $this->cashbox?->code,
                'name' => $this->cashbox?->name,
            ],
            'opened_by' => [
                'id' => $this->openedBy?->id,
                'name' => $this->openedBy?->name,
                'dealer_id' => $this->openedBy?->dealer_id,
                'branch_code' => $this->openedBy?->branch_code,
                'branch_name' => $this->openedBy?->branch_name,
                'region_code' => $this->openedBy?->region_code,
                'region_name' => $this->openedBy?->region_name,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
