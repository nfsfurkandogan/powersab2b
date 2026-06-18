<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class OrderBalanceReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $statuses = $this->input('statuses');

        if (is_string($statuses)) {
            $statuses = collect(explode(',', $statuses))
                ->map(fn ($item) => strtolower(trim((string) $item)))
                ->filter()
                ->values()
                ->all();
        }

        if (is_array($statuses)) {
            $statuses = collect($statuses)
                ->map(fn ($item) => strtolower(trim((string) $item)))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge([
            'statuses' => $statuses,
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
            'dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'async' => ['nullable', 'boolean'],
        ];
    }
}
