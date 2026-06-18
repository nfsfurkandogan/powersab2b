<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePosSaleRequest extends FormRequest
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
            'pos_session_id' => ['required', 'integer', 'exists:pos_sessions,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'sale_type' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'document_type' => ['required', Rule::in(['invoice', 'delivery'])],
            'receipt_no' => ['nullable', 'string', 'max:64'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.meta_json' => ['nullable', 'array'],
        ];
    }
}
