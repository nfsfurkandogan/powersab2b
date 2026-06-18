<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class CustomerAccessScopeService
{
    public function applyToCustomerQuery(
        Builder $query,
        User $user,
        string $table = 'customers',
        bool $selectionMode = false
    ): Builder {
        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return $query;
        }

        if ($user->dealer_id === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->where("{$table}.dealer_id", (int) $user->dealer_id);
        $this->applyLogoCustomerFilters($query, $user, $table);

        if ($this->hasLogoCustomerFilter($user)) {
            return $query;
        }

        $selectedCustomerId = $this->selectedCustomerId($user);

        return match ($user->effectiveCustomerScope()) {
            'assigned' => $this->applyScopedVisibility(
                $query,
                $table,
                $selectedCustomerId,
                fn (Builder $builder) => $builder->where("{$table}.salesperson_user_id", (int) $user->id),
            ),
            'region' => $this->applyScopedVisibility(
                $query,
                $table,
                $selectedCustomerId,
                fn (Builder $builder) => $this->applyRegionScope($builder, $table, $user->region_code),
            ),
            'branch' => $this->applyScopedVisibility(
                $query,
                $table,
                $selectedCustomerId,
                fn (Builder $builder) => $this->applyBranchScope($builder, $table, $user),
            ),
            default => $query,
        };
    }

    public function applyToCustomerOwnedQuery($query, User $user, string $customerIdColumn = 'customer_id')
    {
        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return $query;
        }

        return $query->whereIn($customerIdColumn, $this->accessibleCustomerIdsQuery($user));
    }

    public function accessibleCustomerIdsQuery(User $user): Builder
    {
        return $this->applyToCustomerQuery(
            Customer::query()->select('customers.id'),
            $user
        );
    }

    public function canAccessCustomer(User $user, Customer $customer): bool
    {
        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        if ($user->dealer_id === null || (int) $user->dealer_id !== (int) $customer->dealer_id) {
            return false;
        }

        if (! $this->matchesLogoCustomerFilters($user, $customer)) {
            return false;
        }

        if ($user->hasRole('salesperson') && $this->hasLogoCustomerFilter($user)) {
            return true;
        }

        if ($this->selectedCustomerId($user) === (int) $customer->id) {
            return true;
        }

        return match ($user->effectiveCustomerScope()) {
            'assigned' => (int) $customer->salesperson_user_id === (int) $user->id,
            'region' => $this->sameCode($user->region_code, $customer->region_code),
            'branch' => $this->sameCode($user->branch_code, $customer->branch_code)
                || $this->canAccessLogoBatumCustomer($user, $customer)
                || $this->canAccessUnassignedLogoCustomer($user, $customer),
            default => true,
        };
    }

    public function canSelectCustomer(User $user, Customer $customer): bool
    {
        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        if ($user->dealer_id === null || (int) $user->dealer_id !== (int) $customer->dealer_id) {
            return false;
        }

        if ($user->hasRole('salesperson')) {
            return $this->matchesLogoCustomerFilters($user, $customer);
        }

        return $this->canAccessCustomer($user, $customer);
    }

    private function applyScopedVisibility(
        Builder $query,
        string $table,
        ?int $selectedCustomerId,
        Closure $scope
    ): Builder {
        return $query->where(function (Builder $builder) use ($scope, $table, $selectedCustomerId): void {
            $scope($builder);

            if ($selectedCustomerId !== null) {
                $builder->orWhere("{$table}.id", $selectedCustomerId);
            }
        });
    }

    private function applyRegionScope(Builder $query, string $table, ?string $regionCode): Builder
    {
        $normalized = $this->normalizeCode($regionCode);

        if ($normalized === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where("{$table}.region_code", $normalized);
    }

    private function applyBranchScope(Builder $query, string $table, User $user): Builder
    {
        $normalized = $this->normalizeCode($user->branch_code);

        if ($normalized === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($table, $normalized, $user): void {
            $builder->where("{$table}.branch_code", $normalized);

            if ($this->usesLogoBatumBranchScope($user)) {
                $builder->orWhere(fn (Builder $logoBuilder) => $this->applyLogoBatumScope($logoBuilder, $table));
            } elseif ($this->canPointUseUnassignedLogoCustomers($user)) {
                $builder->orWhere(function (Builder $logoBuilder) use ($table): void {
                    $logoBuilder
                        ->where("{$table}.source_system", 'logo')
                        ->where(function (Builder $branchBuilder) use ($table): void {
                            $branchBuilder
                                ->whereNull("{$table}.branch_code")
                                ->orWhere("{$table}.branch_code", '');
                        });
                });
            }
        });
    }

    private function applyLogoBatumScope(Builder $query, string $table): Builder
    {
        return $query
            ->where("{$table}.source_system", 'logo')
            ->where(function (Builder $builder) use ($table): void {
                $builder
                    ->whereIn("{$table}.meta->integrations->logo->payload->cyphcode", $this->caseVariants(['120-BATUM']))
                    ->orWhereIn("{$table}.meta->integrations->logo->payload->raw->CYPHCODE", $this->caseVariants(['120-BATUM']))
                    ->orWhereIn("{$table}.meta->integrations->logo->payload->town", $this->caseVariants(['BATUMI']))
                    ->orWhereIn("{$table}.meta->integrations->logo->payload->raw->TOWN", $this->caseVariants(['BATUMI']))
                    ->orWhereIn("{$table}.meta->integrations->logo->payload->raw->CITY", $this->caseVariants(['BATUMI']))
                    ->orWhereIn("{$table}.meta->integrations->logo->payload->raw->TAXOFFICE", $this->caseVariants(['BATUM']))
                    ->orWhereIn("{$table}.meta->integrations->logo->payload->raw->INCHARGE", $this->caseVariants(['BATUM']));
            });
    }

    private function applyLogoCustomerFilters(Builder $query, User $user, string $table): Builder
    {
        $logoSpecode4Values = $this->normalizeCodeList($user->logo_customer_specode4);

        if ($logoSpecode4Values === []) {
            return $query;
        }

        $queryValues = $this->caseVariants($logoSpecode4Values);

        return $query->where(function (Builder $builder) use ($table, $queryValues): void {
            $builder
                ->whereIn("{$table}.meta->integrations->logo->payload->specode4", $queryValues)
                ->orWhereIn("{$table}.meta->integrations->logo->payload->raw->SPECODE4", $queryValues);
        });
    }

    private function matchesLogoCustomerFilters(User $user, Customer $customer): bool
    {
        $logoSpecode4Values = $this->normalizeCodeList($user->logo_customer_specode4);

        if ($logoSpecode4Values === []) {
            return true;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $customerSpecode4 = $this->normalizeCode(
            Arr::get($meta, 'integrations.logo.payload.specode4')
            ?? Arr::get($meta, 'integrations.logo.payload.raw.SPECODE4')
        );

        return $customerSpecode4 !== null && in_array($customerSpecode4, $logoSpecode4Values, true);
    }

    private function hasLogoCustomerFilter(User $user): bool
    {
        return $this->normalizeCodeList($user->logo_customer_specode4) !== [];
    }

    private function canAccessUnassignedLogoCustomer(User $user, Customer $customer): bool
    {
        if ($this->usesLogoBatumBranchScope($user)) {
            return false;
        }

        return $this->canPointUseUnassignedLogoCustomers($user)
            && $customer->source_system === 'logo'
            && $this->normalizeCode($customer->branch_code) === null;
    }

    private function canAccessLogoBatumCustomer(User $user, Customer $customer): bool
    {
        return $this->usesLogoBatumBranchScope($user)
            && $this->isLogoBatumCustomer($customer);
    }

    private function usesLogoBatumBranchScope(User $user): bool
    {
        return $this->normalizeCode($user->branch_code) === 'BATUM';
    }

    private function isLogoBatumCustomer(Customer $customer): bool
    {
        foreach ([
            $customer->code,
            $customer->branch_code,
            $customer->region_code,
            $customer->city,
            $customer->district,
            $customer->name,
        ] as $value) {
            $normalized = $this->normalizeCode($value);

            if ($normalized === 'BATUM' || $normalized === 'BATUMI' || str_contains((string) $normalized, 'BATUM')) {
                return true;
            }
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $payload = Arr::get($meta, 'integrations.logo.payload', []);

        if (! is_array($payload)) {
            return false;
        }

        $cyphcode = $this->normalizeCode(
            Arr::get($payload, 'cyphcode')
            ?? Arr::get($payload, 'raw.CYPHCODE')
        );

        if ($cyphcode === '120-BATUM') {
            return true;
        }

        foreach ([
            Arr::get($payload, 'town'),
            Arr::get($payload, 'raw.TOWN'),
            Arr::get($payload, 'raw.CITY'),
        ] as $value) {
            if ($this->normalizeCode($value) === 'BATUMI') {
                return true;
            }
        }

        foreach ([
            Arr::get($payload, 'raw.TAXOFFICE'),
            Arr::get($payload, 'raw.INCHARGE'),
        ] as $value) {
            if ($this->normalizeCode($value) === 'BATUM') {
                return true;
            }
        }

        return false;
    }

    private function canPointUseUnassignedLogoCustomers(User $user): bool
    {
        return $user->hasAnyRole(['point', 'cashier']);
    }

    private function sameCode(?string $left, ?string $right): bool
    {
        $normalizedLeft = $this->normalizeCode($left);
        $normalizedRight = $this->normalizeCode($right);

        return $normalizedLeft !== null && $normalizedLeft === $normalizedRight;
    }

    private function normalizeCode(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return mb_strtoupper($normalized);
    }

    /**
     * @return list<string>
     */
    private function normalizeCodeList(?string $value): array
    {
        $normalized = [];

        foreach (preg_split('/[,;|]+/', (string) $value) ?: [] as $entry) {
            $code = $this->normalizeCode($entry);

            if ($code !== null && ! in_array($code, $normalized, true)) {
                $normalized[] = $code;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function caseVariants(array $values): array
    {
        $variants = [];

        foreach ($values as $value) {
            foreach ([$value, strtolower($value)] as $variant) {
                if (! in_array($variant, $variants, true)) {
                    $variants[] = $variant;
                }
            }
        }

        return $variants;
    }

    private function selectedCustomerId(User $user): ?int
    {
        if (! is_numeric($user->selected_customer_id)) {
            return null;
        }

        $selectedCustomerId = (int) $user->selected_customer_id;

        return $selectedCustomerId > 0 ? $selectedCustomerId : null;
    }
}
