<?php

namespace App\Services\Integrations\Logo;

use App\Models\Cashbox;
use App\Models\Dealer;
use App\Models\IntegrationSyncState;
use App\Models\PosExpense;
use App\Models\PosSession;
use App\Services\Integrations\IntegrationSyncStateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogoPosExpenseImportService
{
    public function __construct(
        private readonly IntegrationSyncStateService $syncState
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public function sync(array $payload): array
    {
        $defaultDealer = $this->resolveDealer(
            $payload['dealer_id'] ?? null,
            $payload['dealer_code'] ?? null,
        );

        $summary = [
            'received' => count($payload['records'] ?? []),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        DB::transaction(function () use ($payload, $defaultDealer, &$summary): void {
            foreach ((array) ($payload['records'] ?? []) as $index => $record) {
                $cashboxCode = $this->nullableString($record['cashbox_code'] ?? null)
                    ?? $this->nullableString($payload['cashbox_code'] ?? null);
                $cashboxName = $this->nullableString($record['cashbox_name'] ?? null)
                    ?? $this->nullableString($payload['cashbox_name'] ?? null);

                $session = $this->resolveOpenSession(
                    $record['pos_session_id'] ?? null,
                    $cashboxCode,
                    $cashboxName,
                    $index,
                );

                $dealer = $this->resolveDealer(
                    $record['dealer_id'] ?? null,
                    $record['dealer_code'] ?? null,
                    $defaultDealer,
                ) ?? $session->openedBy?->dealer;

                if (! $dealer instanceof Dealer) {
                    throw ValidationException::withMessages([
                        "records.$index.dealer_id" => ['Logo POS masrafi icin eslesen bayi bulunamadi.'],
                    ]);
                }

                $externalReference = (string) $record['external_ref'];
                $expense = $this->findExistingExpense($externalReference);
                $attributes = [
                    'pos_session_id' => $session->id,
                    'dealer_id' => $dealer->id,
                    'expense_date' => (string) $record['expense_date'],
                    'category' => $this->nullableString($record['category'] ?? null) ?? 'Logo Masraf',
                    'amount' => number_format((float) $record['amount'], 2, '.', ''),
                    'currency' => strtoupper((string) ($record['currency'] ?? 'GEL')),
                    'note' => $this->nullableString($record['note'] ?? null),
                    'created_by_user_id' => null,
                    'meta' => $this->buildMeta($expense, $record, $session, $cashboxCode, $cashboxName),
                ];

                if ($expense instanceof PosExpense) {
                    $expense->fill($attributes)->save();
                    $summary['updated']++;
                } else {
                    $expense = PosExpense::query()->create($attributes);
                    $summary['created']++;
                }

                $this->syncState->record(
                    system: 'logo',
                    domain: 'pos-expenses',
                    direction: 'inbound',
                    entity: $expense,
                    externalRef: $externalReference,
                    status: 'synced',
                    meta: [
                        'operation' => $expense->wasRecentlyCreated ? 'created' : 'updated',
                        'cashbox_code' => $cashboxCode,
                        'reference_no' => $this->nullableString($record['reference_no'] ?? null),
                    ],
                    payload: $record,
                );
            }
        });

        return $summary;
    }

    private function resolveOpenSession(mixed $sessionId, ?string $cashboxCode, ?string $cashboxName, int $index): PosSession
    {
        if ($sessionId !== null && $sessionId !== '') {
            $session = PosSession::query()
                ->with(['cashbox', 'openedBy.dealer'])
                ->where('status', 'open')
                ->find((int) $sessionId);

            if ($session instanceof PosSession) {
                return $session;
            }
        }

        $cashbox = $this->resolveCashbox($cashboxCode, $cashboxName);

        if (! $cashbox instanceof Cashbox) {
            throw ValidationException::withMessages([
                "records.$index.cashbox_code" => ['Logo POS masrafi icin eslesen aktif kasa bulunamadi.'],
            ]);
        }

        $session = PosSession::query()
            ->with(['cashbox', 'openedBy.dealer'])
            ->where('cashbox_id', $cashbox->id)
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->first();

        if (! $session instanceof PosSession) {
            throw ValidationException::withMessages([
                "records.$index.pos_session_id" => ['Logo POS masrafi icin acik kasa oturumu bulunamadi.'],
            ]);
        }

        return $session;
    }

    private function resolveCashbox(?string $code, ?string $name): ?Cashbox
    {
        $query = Cashbox::query()->where('is_active', true);

        if ($code !== null) {
            $cashbox = (clone $query)->where('code', $code)->first();
            if ($cashbox instanceof Cashbox) {
                return $cashbox;
            }
        }

        if ($name !== null) {
            return (clone $query)
                ->where(fn (Builder $q) => $q
                    ->where('name', $name)
                    ->orWhere('name', 'like', '%'.$name.'%'))
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    private function findExistingExpense(string $externalReference): ?PosExpense
    {
        $state = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'pos-expenses')
            ->where('direction', 'inbound')
            ->where('entity_type', PosExpense::class)
            ->where('external_ref', $externalReference)
            ->latest('id')
            ->first();

        if (! $state instanceof IntegrationSyncState) {
            return null;
        }

        return PosExpense::query()->find((int) $state->entity_id);
    }

    private function resolveDealer(mixed $dealerId, mixed $dealerCode, ?Dealer $fallback = null): ?Dealer
    {
        if ($dealerId !== null && $dealerId !== '') {
            return Dealer::query()->find((int) $dealerId);
        }

        $normalizedDealerCode = $this->nullableString($dealerCode);
        if ($normalizedDealerCode !== null) {
            return Dealer::query()
                ->where('code', $normalizedDealerCode)
                ->first();
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function buildMeta(?PosExpense $existing, array $record, PosSession $session, ?string $cashboxCode, ?string $cashboxName): array
    {
        $existingMeta = $existing && is_array($existing->meta) ? $existing->meta : [];

        return array_replace_recursive($existingMeta, [
            'source_system' => 'logo',
            'source' => 'logo',
            'scope' => 'batum',
            'cashbox_code' => $cashboxCode ?? $session->cashbox?->code,
            'cashbox_name' => $cashboxName ?? $session->cashbox?->name,
            'logo' => [
                'external_ref' => (string) $record['external_ref'],
                'reference_no' => $this->nullableString($record['reference_no'] ?? null),
                'raw' => is_array($record['meta'] ?? null) ? $record['meta'] : [],
            ],
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
}
