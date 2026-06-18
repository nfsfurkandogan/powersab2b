<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SyncLogoLedgerEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuredKey = trim((string) config('integrations.logo.ledger_sync_key', ''));
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
            'records' => ['required', 'array', 'min:1', 'max:1000'],
            'records.*.dealer_id' => ['nullable', 'integer', 'exists:dealers,id'],
            'records.*.dealer_code' => ['nullable', 'string', 'max:64', Rule::exists('dealers', 'code')],
            'records.*.customer_code' => ['nullable', 'string', 'max:64'],
            'records.*.customer_external_ref' => ['nullable', 'string', 'max:128'],
            'records.*.external_ref' => ['required', 'string', 'max:128'],
            'records.*.date' => ['required', 'date'],
            'records.*.type' => ['required', Rule::in(['invoice', 'payment', 'credit', 'debit'])],
            'records.*.debit' => ['nullable', 'numeric', 'min:0'],
            'records.*.credit' => ['nullable', 'numeric', 'min:0'],
            'records.*.balance_after' => ['nullable', 'numeric'],
            'records.*.currency' => ['nullable', 'string', 'size:3'],
            'records.*.reference_no' => ['nullable', 'string', 'max:120'],
            'records.*.description' => ['nullable', 'string', 'max:2000'],
            'records.*.meta' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasDefaultDealer = $this->filled('dealer_id') || $this->filled('dealer_code');

            foreach ((array) $this->input('records', []) as $index => $record) {
                $hasDealer = $hasDefaultDealer || ! empty($record['dealer_id']) || ! empty($record['dealer_code']);
                if (! $hasDealer) {
                    $validator->errors()->add(
                        "records.$index.dealer_id",
                        'Her hareket icin dealer_id veya dealer_code gonderilmelidir.'
                    );
                }

                $hasCustomer = ! empty($record['customer_code']) || ! empty($record['customer_external_ref']);
                if (! $hasCustomer) {
                    $validator->errors()->add(
                        "records.$index.customer_code",
                        'Her hareket icin customer_code veya customer_external_ref gonderilmelidir.'
                    );
                }

                $debit = is_numeric($record['debit'] ?? null) ? (float) $record['debit'] : 0.0;
                $credit = is_numeric($record['credit'] ?? null) ? (float) $record['credit'] : 0.0;

                if ($debit <= 0 && $credit <= 0) {
                    $validator->errors()->add(
                        "records.$index.debit",
                        'Her hareket icin debit veya credit alanlarindan biri pozitif olmalidir.'
                    );
                }

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add(
                        "records.$index.credit",
                        'Ayni harekette hem debit hem credit pozitif olamaz.'
                    );
                }
            }
        });
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Unauthorized integration request.',
        ], 401));
    }
}
