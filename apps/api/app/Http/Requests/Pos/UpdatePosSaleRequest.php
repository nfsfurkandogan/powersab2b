<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePosSaleRequest extends FormRequest
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
            'sale_type' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'receipt_no' => ['nullable', 'string', 'max:64'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:pos_sale_items,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['required', 'array', 'size:1'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.meta_json' => ['nullable', 'array'],
        ];
    }
}
