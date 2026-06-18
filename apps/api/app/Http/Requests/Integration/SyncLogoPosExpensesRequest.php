<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SyncLogoPosExpensesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuredKey = trim((string) config('integrations.logo.pos_expense_sync_key', ''));
        $providedKey = trim((string) $this->header('X-Integration-Key', ''));

        return $configuredKey !== '' && $providedKey !== '' && hash_equals($configuredKey, $providedKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'dealer_code' => ['nullable', 'string', 'max:64', Rule::exists('dealers', 'code')],
            'cashbox_code' => ['nullable', 'string', 'max:64'],
            'cashbox_name' => ['nullable', 'string', 'max:255'],
            'records' => ['required', 'array', 'min:1', 'max:1000'],
            'records.*.dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'records.*.dealer_code' => ['nullable', 'string', 'max:64', Rule::exists('dealers', 'code')],
            'records.*.pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'records.*.cashbox_code' => ['nullable', 'string', 'max:64'],
            'records.*.cashbox_name' => ['nullable', 'string', 'max:255'],
            'records.*.external_ref' => ['required', 'string', 'max:128'],
            'records.*.expense_date' => ['required', 'date'],
            'records.*.category' => ['nullable', 'string', 'max:80'],
            'records.*.amount' => ['required', 'numeric', 'gt:0'],
            'records.*.currency' => ['nullable', 'string', 'size:3'],
            'records.*.note' => ['nullable', 'string', 'max:255'],
            'records.*.reference_no' => ['nullable', 'string', 'max:120'],
            'records.*.meta' => ['nullable', 'array'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Unauthorized integration request.',
        ], 401));
    }
}
