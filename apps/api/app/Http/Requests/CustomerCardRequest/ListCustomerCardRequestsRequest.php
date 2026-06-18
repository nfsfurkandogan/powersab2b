<?php

namespace App\Http\Requests\CustomerCardRequest;

use App\Models\CustomerCardRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCustomerCardRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('status') && ! $this->has('statuses')) {
            $this->merge([
                'statuses' => [$this->input('status')],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(CustomerCardRequest::statuses())],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', Rule::in(CustomerCardRequest::statuses())],
        ];
    }
}
