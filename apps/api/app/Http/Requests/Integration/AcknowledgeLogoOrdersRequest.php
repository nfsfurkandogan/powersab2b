<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AcknowledgeLogoOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $configuredKey = trim((string) config('integrations.logo.order_sync_key', ''));
        $providedKey = trim((string) $this->header('X-Integration-Key', ''));

        return $configuredKey !== '' && $providedKey !== '' && hash_equals($configuredKey, $providedKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1', 'max:1000'],
            'records.*.order_id' => ['required', 'integer', 'exists:orders,id'],
            'records.*.status' => ['required', 'string', Rule::in(['synced', 'failed'])],
            'records.*.external_ref' => ['nullable', 'string', 'max:128'],
            'records.*.error' => ['nullable', 'string', 'max:2000'],
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
