<?php

namespace App\Http\Requests\Context;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ];
    }
}
