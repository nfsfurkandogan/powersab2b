<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $displayName = $this->resolveDisplayTitle($meta);
        $phone = $this->resolveDisplayPhone($meta);
        $city = $this->resolveDisplayLocation($meta, 'city');
        $district = $this->resolveDisplayLocation($meta, 'district');
        [$totalDue, $orderDue, $currency, $balanceSource] = $this->resolveBalanceSummary($meta);

        return [
            'id' => $this->id,
            'dealer_id' => $this->dealer_id,
            'salesperson_user_id' => $this->salesperson_user_id,
            'region_code' => $this->region_code,
            'region_name' => $this->region_name,
            'branch_code' => $this->branch_code,
            'branch_name' => $this->branch_name,
            'source_system' => $this->source_system,
            'source_reference' => $this->source_reference,
            'sync_status' => $this->sync_status,
            'sync_error' => $this->sync_error,
            'code' => $this->code,
            'name' => $displayName,
            'title' => $displayName,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $phone,
            'city' => $city,
            'district' => $district,
            'tax_office' => $this->tax_office,
            'tax_number' => $this->tax_number,
            'credit_limit' => $this->credit_limit,
            'special_discount_rate' => $this->specialDiscountRate($meta),
            'is_active' => (bool) $this->is_active,
            'address' => $meta['address'] ?? $meta['full_address'] ?? null,
            'iban' => $meta['iban'] ?? null,
            'meta' => $meta ?: null,
            'last_synced_at' => $this->last_synced_at,
            'balance_due' => $this->when(
                isset($this->balance_due),
                number_format((float) $this->balance_due, 2, '.', '')
            ),
            'has_draft_cart' => $this->when(
                isset($this->has_draft_cart),
                (bool) $this->has_draft_cart
            ),
            'balance_summary' => $this->when(
                isset($this->total_balance_due)
                    || isset($this->order_balance_due)
                    || isset($this->balance_due)
                    || data_get($meta, 'integrations.logo.financials.total_due') !== null
                    || data_get($meta, 'integrations.logo.financials.order_due') !== null,
                [
                    'total_due' => number_format($totalDue, 2, '.', ''),
                    'order_due' => number_format($orderDue, 2, '.', ''),
                    'currency' => $currency,
                ]
            ),
            'balance_source' => $this->when(
                isset($this->total_balance_due)
                    || isset($this->order_balance_due)
                    || isset($this->balance_due)
                    || data_get($meta, 'integrations.logo.financials.total_due') !== null
                    || data_get($meta, 'integrations.logo.financials.order_due') !== null,
                $balanceSource
            ),
            'has_cart' => $this->when(
                isset($this->has_draft_cart),
                (bool) $this->has_draft_cart
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function specialDiscountRate(array $meta): ?string
    {
        $value = $meta['special_discount_rate'] ?? null;

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveDisplayTitle(array $meta): string
    {
        $current = trim((string) $this->name);
        $code = trim((string) $this->code);

        if ($current !== '' && ! $this->sameText($current, $code)) {
            return $current;
        }

        foreach ($this->logoTitlePaths() as $path) {
            $candidate = trim((string) data_get($meta, $path, ''));

            if ($candidate !== '' && ! $this->sameText($candidate, $code)) {
                return $candidate;
            }
        }

        return $current !== '' ? $current : $code;
    }

    /**
     * @return array<int, string>
     */
    private function logoTitlePaths(): array
    {
        return [
            'integrations.logo.payload.raw.DEFINITION_',
            'integrations.logo.payload.raw.DEFINITION',
            'integrations.logo.payload.raw.DESCRIPTION',
            'integrations.logo.payload.raw.DESC_',
            'integrations.logo.payload.raw.DESC',
            'integrations.logo.payload.raw.ACIKLAMA',
            'integrations.logo.payload.DEFINITION_',
            'integrations.logo.payload.DEFINITION',
            'integrations.logo.payload.description',
            'integrations.logo.payload.title',
        ];
    }

    private function sameText(string $left, string $right): bool
    {
        return mb_strtoupper(trim($left), 'UTF-8') === mb_strtoupper(trim($right), 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveDisplayPhone(array $meta): ?string
    {
        $current = trim((string) $this->phone);

        if ($current !== '') {
            return $current;
        }

        foreach ($this->logoPhonePaths() as $path) {
            $candidate = trim((string) data_get($meta, $path, ''));

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function logoPhonePaths(): array
    {
        return [
            'integrations.logo.payload.raw.TELNRS1',
            'integrations.logo.payload.raw.TELNR1',
            'integrations.logo.payload.raw.PHONE',
            'integrations.logo.payload.raw.PHONE1',
            'integrations.logo.payload.raw.PHONE_1',
            'integrations.logo.payload.raw.TELEFON',
            'integrations.logo.payload.raw.TELEFON1',
            'integrations.logo.payload.raw.GSM',
            'integrations.logo.payload.raw.CEPTEL',
            'integrations.logo.payload.phone',
            'integrations.logo.payload.phone_1',
            'integrations.logo.payload.telephone',
            'integrations.logo.payload.mobile_phone',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveDisplayLocation(array $meta, string $field): ?string
    {
        $current = trim((string) ($field === 'city' ? $this->city : $this->district));

        if ($current !== '') {
            return $current;
        }

        foreach ($this->logoLocationPaths($field) as $path) {
            $candidate = trim((string) data_get($meta, $path, ''));

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function logoLocationPaths(string $field): array
    {
        if ($field === 'city') {
            return [
                'integrations.logo.payload.raw.CITY',
                'integrations.logo.payload.raw.IL',
                'integrations.logo.payload.raw.PROVINCE',
                'integrations.logo.payload.city',
                'integrations.logo.payload.province',
            ];
        }

        return [
            'integrations.logo.payload.raw.TOWN',
            'integrations.logo.payload.raw.DISTRICT',
            'integrations.logo.payload.raw.ILCE',
            'integrations.logo.payload.raw.İLÇE',
            'integrations.logo.payload.raw.COUNTY',
            'integrations.logo.payload.district',
            'integrations.logo.payload.town',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{0: float, 1: float, 2: string, 3: string}
     */
    private function resolveBalanceSummary(array $meta): array
    {
        $logoFinancials = data_get($meta, 'integrations.logo.financials', []);

        $ledgerTotal = isset($this->total_balance_due)
            ? (float) $this->total_balance_due
            : (isset($this->balance_due) ? (float) $this->balance_due : null);
        $ledgerOrder = isset($this->order_balance_due) ? (float) $this->order_balance_due : null;
        $logoTotal = is_numeric($logoFinancials['total_due'] ?? null) ? (float) $logoFinancials['total_due'] : null;
        $logoOrder = is_numeric($logoFinancials['order_due'] ?? null) ? (float) $logoFinancials['order_due'] : null;
        $logoCurrency = strtoupper((string) ($logoFinancials['currency'] ?? 'TRY'));

        $hasInternalBalances = abs((float) ($ledgerTotal ?? 0)) > 0.00001 || abs((float) ($ledgerOrder ?? 0)) > 0.00001;
        $canFallbackToLogo = $this->source_system === 'logo' && ! $hasInternalBalances && ($logoTotal !== null || $logoOrder !== null);

        if ($canFallbackToLogo) {
            return [
                (float) ($logoTotal ?? 0),
                (float) ($logoOrder ?? 0),
                $logoCurrency !== '' ? $logoCurrency : 'TRY',
                'logo',
            ];
        }

        return [
            (float) ($ledgerTotal ?? 0),
            (float) ($ledgerOrder ?? 0),
            'TRY',
            'b2b',
        ];
    }
}
