<?php

namespace App\Policies;

use App\Models\PosSession;
use App\Models\User;
use App\Support\MenuPermissions;

class PosSessionPolicy
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

    public function open(User $user): bool
    {
        return $this->canUsePos($user);
    }

    public function view(User $user, PosSession $session): bool
    {
        return $this->canAccessSession($user, $session);
    }

    public function close(User $user, PosSession $session): bool
    {
        return $session->status === 'open' && $this->canAccessSession($user, $session);
    }

    private function canAccessSession(User $user, PosSession $session): bool
    {
        if (! $this->canUsePos($user)) {
            return false;
        }

        $sessionDealerId = $session->relationLoaded('openedBy')
            ? $session->openedBy?->dealer_id
            : $session->openedBy()->value('dealer_id');

        if ($sessionDealerId === null || (int) $sessionDealerId !== (int) $user->dealer_id) {
            return false;
        }

        if ($this->usesUserScopedCashbox($user)) {
            return (int) $session->opened_by === (int) $user->id;
        }

        return true;
    }

    private function canUsePos(User $user): bool
    {
        return $user->hasAnyRole(['dealer_admin', 'cashier', 'point'])
            || in_array('pos', MenuPermissions::forUser($user), true);
    }

    private function usesUserScopedCashbox(User $user): bool
    {
        if ($user->hasRole('dealer_admin') || $user->hasRole('admin')) {
            return false;
        }

        return $user->hasAnyRole(['cashier', 'point'])
            || in_array('pos', MenuPermissions::forUser($user), true);
    }
}
