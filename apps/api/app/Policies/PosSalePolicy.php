<?php

namespace App\Policies;

use App\Models\PosSale;
use App\Models\User;
use App\Support\MenuPermissions;

class PosSalePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->canUsePos($user);
    }

    public function create(User $user): bool
    {
        return $this->canUsePos($user);
    }

    public function view(User $user, PosSale $sale): bool
    {
        return $this->canAccessSale($user, $sale);
    }

    public function cancel(User $user, PosSale $sale): bool
    {
        return $sale->status === 'paid' && $this->canAccessSale($user, $sale);
    }

    public function update(User $user, PosSale $sale): bool
    {
        return $sale->status === 'paid' && $this->canAccessSale($user, $sale);
    }

    public function delete(User $user, PosSale $sale): bool
    {
        return $this->canAccessSale($user, $sale);
    }

    private function canAccessSale(User $user, PosSale $sale): bool
    {
        if (! $this->canUsePos($user)) {
            return false;
        }

        $saleDealerId = $sale->relationLoaded('customer')
            ? $sale->customer?->dealer_id
            : $sale->customer()->value('dealer_id');

        if ($saleDealerId === null || (int) $saleDealerId !== (int) $user->dealer_id) {
            return false;
        }

        if ($user->hasAnyRole(['cashier', 'point']) && ! $user->hasRole('dealer_admin')) {
            $openedBy = $sale->relationLoaded('posSession')
                ? $sale->posSession?->opened_by
                : $sale->posSession()->value('opened_by');

            return (int) $openedBy === (int) $user->id;
        }

        return true;
    }

    private function canUsePos(User $user): bool
    {
        return $user->hasAnyRole(['dealer_admin', 'cashier', 'point'])
            || in_array('pos', MenuPermissions::forUser($user), true);
    }
}
