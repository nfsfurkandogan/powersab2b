<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class CustomerBalanceReportRequest extends FormRequest
{
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
            'q' => ['nullable', 'string', 'max:120'],
            'date_to' => ['nullable', 'date'],
            'has_balance' => ['nullable', 'boolean'],
            'min_balance' => ['nullable', 'numeric'],
            'max_balance' => ['nullable', 'numeric', 'gte:min_balance'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'async' => ['nullable', 'boolean'],
        ];
    }
}
