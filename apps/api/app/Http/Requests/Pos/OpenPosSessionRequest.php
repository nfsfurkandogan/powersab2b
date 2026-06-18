<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class OpenPosSessionRequest extends FormRequest
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
            'cashbox_id' => ['nullable', 'integer', 'exists:cashboxes,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ];
    }
}
