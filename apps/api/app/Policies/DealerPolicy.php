<?php

namespace App\Policies;

use App\Models\Dealer;
use App\Models\User;

class DealerPolicy
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
        return $user->hasAnyRole(['dealer_admin', 'salesperson', 'cashier', 'point']);
    }

    public function view(User $user, Dealer $dealer): bool
    {
        return $user->hasAnyRole(['dealer_admin', 'salesperson', 'cashier', 'point'])
            && $user->dealer_id === $dealer->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Dealer $dealer): bool
    {
        return $user->hasRole('dealer_admin') && $user->dealer_id === $dealer->id;
    }

    public function delete(User $user, Dealer $dealer): bool
    {
        return false;
    }
}
