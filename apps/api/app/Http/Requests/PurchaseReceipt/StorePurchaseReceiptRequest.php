<?php

namespace App\Http\Requests\PurchaseReceipt;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'document_no' => ['nullable', 'string', 'max:64'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'warehouse_code' => ['nullable', 'string', 'max:64'],
            'warehouse_name' => ['nullable', 'string', 'max:160'],
            'received_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:250'],
            'items.*.product_code' => ['nullable', 'string', 'max:96'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.expected_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'items.*.accepted_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_at.required' => 'Kabul tarihi zorunlu.',
            'items.required' => 'Mal kabul icin en az bir urun satiri ekleyin.',
            'items.min' => 'Mal kabul icin en az bir urun satiri ekleyin.',
            'items.*.product_name.required' => 'Urun adi zorunlu.',
        ];
    }
}
