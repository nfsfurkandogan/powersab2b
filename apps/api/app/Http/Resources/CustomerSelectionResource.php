<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Pricing\DisplayCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSelectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $title = $this->resolveDisplayTitle($meta);
        $phone = $this->resolveDisplayPhone($meta);
        $city = $this->resolveDisplayLocation($meta, 'city');
        $district = $this->resolveDisplayLocation($meta, 'district');
        $user = $request->user();
        [$totalDue, $orderDue, $currency, $balanceSource] = $this->resolveBalanceSummary(
            $user instanceof User ? $user : null
        );

        return [
            'id' => $this->id,
            'dealer_id' => $this->dealer_id !== null ? (int) $this->dealer_id : null,
            'code' => $this->code,
            'title' => $title,
            'city' => $city,
            'district' => $district,
            'phone' => $phone,
            'region_code' => $this->region_code,
            'region_name' => $this->region_name,
            'branch_code' => $this->branch_code,
            'branch_name' => $this->branch_name,
            'source_system' => $this->source_system,
            'source_reference' => $this->source_reference,
            'last_synced_at' => $this->last_synced_at,
            'balance_summary' => [
                'total_due' => number_format($totalDue, 2, '.', ''),
                'order_due' => number_format($orderDue, 2, '.', ''),
                'currency' => $currency,
            ],
            'balance_source' => $balanceSource,
            'has_cart' => (bool) ($this->has_draft_cart ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveDisplayTitle(array $meta): string
    {
        $current = $this->nullableLogoText($this->name) ?? '';
        $code = $this->nullableLogoText($this->code) ?? '';

        if ($current !== '' && ! $this->sameText($current, $code)) {
            return $current;
        }

        foreach ($this->logoTitlePaths() as $path) {
            $candidate = $this->nullableLogoText(data_get($meta, $path));

            if ($candidate !== null && ! $this->sameText($candidate, $code)) {
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

    private function nullableLogoText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return in_array(mb_strtoupper($normalized, 'UTF-8'), ['NULL', 'NIL', 'N/A', 'YOK', '-'], true)
            ? null
            : $normalized;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveDisplayPhone(array $meta): ?string
    {
        $current = $this->nullableLogoText($this->phone) ?? '';

        if ($current !== '') {
            return $current;
        }

        foreach ($this->logoPhonePaths() as $path) {
            $candidate = $this->nullableLogoText(data_get($meta, $path));

            if ($candidate !== null) {
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
        $current = $this->nullableLogoText($field === 'city' ? $this->city : $this->district) ?? '';

        if ($current !== '') {
            return $current;
        }

        foreach ($this->logoLocationPaths($field) as $path) {
            $candidate = $this->nullableLogoText(data_get($meta, $path));

            if ($candidate !== null) {
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
     * @return array{0: float, 1: float, 2: string, 3: string}
     */
    private function resolveBalanceSummary(?User $user): array
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $logoFinancials = data_get($meta, 'integrations.logo.financials', []);

        $ledgerTotal = isset($this->total_balance_due) ? (float) $this->total_balance_due : null;
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
                $this->displayBalanceCurrency($logoCurrency !== '' ? $logoCurrency : 'TRY', $user),
                'logo',
            ];
        }

        return [
            (float) ($ledgerTotal ?? 0),
            (float) ($ledgerOrder ?? 0),
            $this->displayBalanceCurrency('TRY', $user),
            'b2b',
        ];
    }

    private function displayBalanceCurrency(string $currency, ?User $user): string
    {
        if ($this->usesLariCustomerDisplay($user)) {
            return 'GEL';
        }

        return DisplayCurrency::normalize($currency, $user);
    }

    private function usesLariCustomerDisplay(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $this->normalizeUserCode($user->branch_code) === 'BATUM'
            || $this->normalizeUserCode($user->region_code) === 'BATUM';
    }

    private function normalizeUserCode(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? mb_strtoupper($normalized, 'UTF-8') : null;
    }
}
