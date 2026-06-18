<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $breakdown = $this->input('breakdown');
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
            'breakdown' => is_string($breakdown) ? strtolower($breakdown) : $breakdown,
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'breakdown' => ['nullable', Rule::in(['product', 'brand', 'customer'])],
            'q' => ['nullable', 'string', 'max:120'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'async' => ['nullable', 'boolean'],
        ];
    }
}
