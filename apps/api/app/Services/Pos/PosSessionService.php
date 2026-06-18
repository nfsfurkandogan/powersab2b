<?php

namespace App\Services\Pos;

use App\Models\Cashbox;
use App\Models\PosSession;
use App\Models\User;
use App\Support\MenuPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosSessionService
{
    public function open(User $user, ?int $cashboxId, float $openingCash): PosSession
    {
        return DB::transaction(function () use ($user, $cashboxId, $openingCash) {
            $cashbox = $this->resolveCashbox($user, $cashboxId);
            $cashboxId = (int) $cashbox->getKey();

            $hasOpenSession = PosSession::query()
                ->where('cashbox_id', $cashboxId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists();

            if ($hasOpenSession) {
                throw ValidationException::withMessages([
                    'cashbox_id' => ['There is already an open POS session for this cashbox.'],
                ]);
            }

            $session = PosSession::create([
                'cashbox_id' => $cashboxId,
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_cash' => number_format(max(0, $openingCash), 2, '.', ''),
                'status' => 'open',
            ]);

            return $session->fresh(['cashbox', 'openedBy']);
        });
    }

    private function resolveCashbox(User $user, ?int $cashboxId): Cashbox
    {
        if ($cashboxId !== null) {
            $cashbox = Cashbox::query()->lockForUpdate()->find($cashboxId);

            if ($cashbox === null || ! $cashbox->is_active) {
                throw ValidationException::withMessages([
                    'cashbox_id' => ['Cashbox not found or inactive.'],
                ]);
            }

            return $cashbox;
        }

        if ($this->usesUserScopedCashbox($user)) {
            return $this->resolvePointCashbox($user);
        }

        $cashbox = Cashbox::query()
            ->lockForUpdate()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($cashbox instanceof Cashbox) {
            return $cashbox;
        }

        $defaultCashbox = Cashbox::query()
            ->lockForUpdate()
            ->where('code', 'MAIN-POS')
            ->first();

        if ($defaultCashbox instanceof Cashbox) {
            if (! $defaultCashbox->is_active) {
                $defaultCashbox->forceFill(['is_active' => true])->save();
            }

            return $defaultCashbox;
        }

        return Cashbox::query()->create([
            'code' => 'MAIN-POS',
            'name' => 'Ana POS Kasasi',
            'is_active' => true,
        ]);
    }

    private function resolvePointCashbox(User $user): Cashbox
    {
        $userCashboxCode = $this->nullableString($user->logo_cashbox_code);
        $code = $userCashboxCode ?? 'POINT-'.$user->id;
        $name = $this->nullableString($user->logo_cashbox_name)
            ?? trim(($user->name ?: 'Point').' Kasasi');

        return $this->resolvePointCashboxByCode($code, $name);
    }

    private function resolvePointCashboxByCode(string $code, string $name): Cashbox
    {
        $cashbox = Cashbox::query()
            ->lockForUpdate()
            ->where('code', $code)
            ->first();

        if ($cashbox instanceof Cashbox) {
            $updates = [];

            if (! $cashbox->is_active) {
                $updates['is_active'] = true;
            }

            if ($this->nullableString($cashbox->name) !== $name) {
                $updates['name'] = $name;
            }

            if ($updates !== []) {
                $cashbox->forceFill($updates)->save();
            }

            return $cashbox;
        }

        return Cashbox::query()->create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function closeCurrent(User $user, float $closingCashCounted, ?int $cashboxId = null): PosSession
    {
        return DB::transaction(function () use ($user, $closingCashCounted, $cashboxId) {
            $session = $this->currentQuery($user, $cashboxId)
                ->lockForUpdate()
                ->first();

            if (! $session instanceof PosSession) {
                throw ValidationException::withMessages([
                    'session' => ['No open POS session found for current scope.'],
                ]);
            }

            $session->status = 'closed';
            $session->closed_at = now();
            $session->closing_cash_counted = number_format(max(0, $closingCashCounted), 2, '.', '');
            $session->save();

            return $session->fresh(['cashbox', 'openedBy']);
        });
    }

    public function current(User $user, ?int $cashboxId = null): ?PosSession
    {
        return $this->currentQuery($user, $cashboxId)->first();
    }

    /**
     * @return Builder<PosSession>
     */
    public function currentQuery(User $user, ?int $cashboxId = null): Builder
    {
        $query = PosSession::query()
            ->with(['cashbox', 'openedBy'])
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->orderByDesc('id');

        if (! $user->hasRole('admin')) {
            $query->whereHas('openedBy', fn (Builder $q) => $q->where('dealer_id', $user->dealer_id));

            if ($this->usesUserScopedCashbox($user)) {
                $query->where('opened_by', $user->id);
            }
        }

        if ($cashboxId !== null) {
            $query->where('cashbox_id', $cashboxId);
        }

        return $query;
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
