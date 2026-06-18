<?php

namespace App\Http\Requests\CustomerCardRequest;

use App\Models\CustomerCardRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerCardRequestStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(CustomerCardRequest::statuses())],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
