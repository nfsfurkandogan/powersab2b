<?php

namespace App\Http\Requests\ReturnRequest;

use App\Models\ReturnRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequestStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(ReturnRequest::statuses())],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
