<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPosSalesRequest extends FormRequest
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
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'cashbox_id' => ['nullable', 'integer', 'exists:cashboxes,id'],
            'status' => ['nullable', Rule::in(['paid', 'cancelled'])],
            'document_type' => ['nullable', Rule::in(['invoice', 'delivery'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'cursor' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
