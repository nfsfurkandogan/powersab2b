<?php

namespace App\Http\Requests\ReturnRequest;

use App\Models\ReturnRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $requestType = $this->input('request_type', $this->input('type'));
        $reasonCode = $this->input('reason_code', $this->input('reason'));

        $this->merge([
            'request_type' => is_string($requestType) ? strtolower(trim($requestType)) : $requestType,
            'reason_code' => is_string($reasonCode) ? strtolower(trim($reasonCode)) : $reasonCode,
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
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'request_type' => ['required', Rule::in(ReturnRequest::types())],
            'type' => ['nullable', 'string'],
            'reason_code' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:64'],
            'reason_note' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
