<?php

namespace App\Services\Pos;

use App\Models\PosExpense;
use App\Models\PosSession;
use App\Models\User;
use App\Services\Integrations\IntegrationSyncStateService;
use App\Support\MenuPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosExpenseService
{
    private const POINT_CURRENCY = 'GEL';

    public function __construct(
        private readonly IntegrationSyncStateService $syncState
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $user, array $payload): PosExpense
    {
        return DB::transaction(function () use ($user, $payload): PosExpense {
            $session = PosSession::query()
                ->with(['openedBy', 'cashbox'])
                ->lockForUpdate()
                ->find((int) $payload['pos_session_id']);

            if (! $session instanceof PosSession || $session->status !== 'open') {
                throw ValidationException::withMessages([
                    'pos_session_id' => ['POS session not found or not open.'],
                ]);
            }

            $this->assertCanOperateSession($user, $session);

            $expense = PosExpense::query()->create([
                'pos_session_id' => $session->id,
                'dealer_id' => (int) ($session->openedBy?->dealer_id ?? $user->dealer_id),
                'expense_date' => $payload['expense_date'] ?? now()->toDateString(),
                'category' => trim((string) $payload['category']),
                'amount' => number_format((float) $payload['amount'], 2, '.', ''),
                'currency' => self::POINT_CURRENCY,
                'note' => filled($payload['note'] ?? null) ? trim((string) $payload['note']) : null,
                'created_by_user_id' => $user->id,
                'meta' => $payload['meta'] ?? null,
            ]);

            $this->queueExpenseForLogoExport($expense);

            return $expense->fresh(['posSession.cashbox', 'createdBy']);
        });
    }

    /**
     * @param  Builder<PosExpense>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<PosExpense>
     */
    public function applyFilters(Builder $query, User $user, array $filters): Builder
    {
        if (! $user->hasRole('admin')) {
            $query->where('dealer_id', $user->dealer_id);

            if ($user->hasAnyRole(['cashier', 'point']) && ! $user->hasRole('dealer_admin')) {
                $query->whereHas('posSession', fn (Builder $q) => $q->where('opened_by', $user->id));
            }
        }

        if (! empty($filters['pos_session_id'])) {
            $query->where('pos_session_id', (int) $filters['pos_session_id']);
        }

        if (! empty($filters['cashbox_id'])) {
            $query->whereHas('posSession', fn (Builder $q) => $q->where('cashbox_id', (int) $filters['cashbox_id']));
        }

        if (! empty($filters['date'])) {
            $query->whereDate('expense_date', (string) $filters['date']);
        } else {
            if (! empty($filters['date_from'])) {
                $query->whereDate('expense_date', '>=', (string) $filters['date_from']);
            }

            if (! empty($filters['date_to'])) {
                $query->whereDate('expense_date', '<=', (string) $filters['date_to']);
            }
        }

        return $query;
    }

    private function assertCanOperateSession(User $user, PosSession $session): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $sessionDealerId = $session->openedBy?->dealer_id;
        if ($sessionDealerId === null || (int) $sessionDealerId !== (int) $user->dealer_id) {
            throw ValidationException::withMessages([
                'pos_session_id' => ['Session is not accessible in your dealer scope.'],
            ]);
        }

        if ($this->usesUserScopedCashbox($user) && (int) $session->opened_by !== (int) $user->id) {
            throw ValidationException::withMessages([
                'pos_session_id' => ['Cashier can only operate own session.'],
            ]);
        }
    }

    private function usesUserScopedCashbox(User $user): bool
    {
        if ($user->hasRole('dealer_admin') || $user->hasRole('admin')) {
            return false;
        }

        return $user->hasAnyRole(['cashier', 'point'])
            || in_array('pos', MenuPermissions::forUser($user), true);
    }

    private function queueExpenseForLogoExport(PosExpense $expense): void
    {
        $this->syncState->record(
            system: 'logo',
            domain: 'pos-expenses',
            direction: 'outbound',
            entity: $expense,
            externalRef: null,
            status: 'queued',
            error: null,
            meta: [
                'export_key' => 'B2B-POSEXP-'.$expense->id,
                'category' => $expense->category,
                'pos_session_id' => $expense->pos_session_id,
            ],
            payload: [
                'pos_expense_id' => $expense->id,
                'category' => $expense->category,
                'amount' => number_format((float) $expense->amount, 2, '.', ''),
                'currency' => $expense->currency,
            ],
        );
    }
}
