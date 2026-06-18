<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerBalanceReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->id,
            'dealer_id' => $this->dealer_id,
            'dealer_name' => $this->dealer_name,
            'code' => $this->code,
            'title' => $this->name,
            'city' => $this->city,
            'district' => $this->district,
            'phone' => $this->phone,
            'balance' => number_format((float) ($this->balance ?? 0), 2, '.', ''),
            'last_movement_date' => $this->last_movement_date,
        ];
    }
}
