<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class ListOrdersRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $statuses = $this->input('statuses');
        $singleStatus = $this->input('status');

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        if (! is_array($statuses)) {
            $statuses = [];
        }

        if (is_string($singleStatus) && trim($singleStatus) !== '') {
            $statuses[] = $singleStatus;
        }

        $normalized = collect($statuses)
            ->map(fn ($status) => strtolower(trim((string) $status)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'statuses' => $normalized,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:64'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'max:64'],
            'dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'cursor' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
