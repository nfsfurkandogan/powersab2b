<?php

namespace App\Http\Requests\ReturnRequest;

use App\Models\ReturnRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReturnRequestsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $statuses = $this->input('statuses');
        $types = $this->input('types');
        $singleStatus = $this->input('status');
        $singleType = $this->input('type');

        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        if (is_string($types)) {
            $types = explode(',', $types);
        }

        if (! is_array($statuses)) {
            $statuses = [];
        }

        if (! is_array($types)) {
            $types = [];
        }

        if (is_string($singleStatus) && trim($singleStatus) !== '') {
            $statuses[] = $singleStatus;
        }

        if (is_string($singleType) && trim($singleType) !== '') {
            $types[] = $singleType;
        }

        $this->merge([
            'statuses' => collect($statuses)
                ->map(fn ($status) => strtolower(trim((string) $status)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'types' => collect($types)
                ->map(fn ($type) => strtolower(trim((string) $type)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
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
            'q' => ['nullable', 'string', 'max:120'],
            'dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'status' => ['nullable', 'string', 'max:64'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', Rule::in(ReturnRequest::statuses())],
            'type' => ['nullable', 'string', 'max:64'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in(ReturnRequest::types())],
            'cursor' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
