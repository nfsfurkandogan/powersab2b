<?php

namespace App\Services\Integrations\Logo;

use App\Models\Dealer;
use App\Models\IntegrationSyncState;
use App\Models\PurchaseReceipt;
use App\Services\Integrations\IntegrationSyncStateService;
use Illuminate\Validation\ValidationException;

class LogoPurchaseReceiptExportService
{
    public function __construct(
        private readonly IntegrationSyncStateService $syncState
    ) {}

    public function pending(array $filters): array
    {
        $dealer = $this->resolveDealer($filters['dealer_id'] ?? null, $filters['dealer_code'] ?? null);
        $statuses = collect((array) ($filters['statuses'] ?? ['queued', 'failed']))
            ->filter(fn ($status) => in_array($status, ['queued', 'failed'], true))
            ->values()
            ->all();
        $statuses = $statuses === [] ? ['queued', 'failed'] : $statuses;
        $limit = min((int) ($filters['limit'] ?? 100), 500);

        $states = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', 'purchase-receipts')
            ->where('direction', 'outbound')
            ->where('entity_type', PurchaseReceipt::class)
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $receipts = PurchaseReceipt::query()
            ->with(['dealer:id,code,name', 'createdBy:id,name', 'items'])
            ->whereIn('id', $states->pluck('entity_id')->map(fn ($id) => (int) $id)->all())
            ->whereNotIn('status', ['cancelled', 'synced'])
            ->get()
            ->keyBy('id');

        $records = $states
            ->map(function (IntegrationSyncState $state) use ($receipts, $dealer): ?array {
                $receipt = $receipts->get((int) $state->entity_id);
                if (! $receipt instanceof PurchaseReceipt) {
                    return null;
                }
                if ($dealer instanceof Dealer && (int) $receipt->dealer_id !== (int) $dealer->id) {
                    return null;
                }

                return $this->transformReceipt($receipt, $state);
            })
            ->filter()
            ->values();

        return [
            'received' => $records->count(),
            'filters' => [
                'dealer_id' => $dealer?->id,
                'statuses' => $statuses,
                'limit' => $limit,
            ],
            'records' => $records->all(),
        ];
    }

    public function acknowledge(array $payload): array
    {
        $summary = [
            'received' => count($payload['records'] ?? []),
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ((array) ($payload['records'] ?? []) as $index => $record) {
            $receipt = PurchaseReceipt::query()->find((int) $record['purchase_receipt_id']);
            if (! $receipt instanceof PurchaseReceipt) {
                throw ValidationException::withMessages([
                    "records.$index.purchase_receipt_id" => ['Mal kabul kaydi bulunamadi.'],
                ]);
            }

            $status = (string) $record['status'];
            $receipt->status = $status === 'synced' ? 'synced' : 'failed';
            $receipt->save();

            $this->syncState->record(
                system: 'logo',
                domain: 'purchase-receipts',
                direction: 'outbound',
                entity: $receipt,
                externalRef: $this->nullableString($record['external_ref'] ?? null),
                status: $status,
                error: $status === 'failed' ? $this->nullableString($record['error'] ?? null) : null,
                meta: [
                    'acknowledged' => true,
                    'export_key' => 'B2B-PURCHASE-'.$receipt->id,
                    'payload' => is_array($record['meta'] ?? null) ? $record['meta'] : [],
                ],
                payload: $record,
                syncedAt: now(),
            );

            $summary[$status]++;
        }

        return $summary;
    }

    private function transformReceipt(PurchaseReceipt $receipt, IntegrationSyncState $state): array
    {
        $acceptedTotal = (int) $receipt->items->sum('accepted_quantity');
        $expectedTotal = (int) $receipt->items->sum('expected_quantity');

        return [
            'purchase_receipt_id' => $receipt->id,
            'export_key' => 'B2B-PURCHASE-'.$receipt->id,
            'dealer_id' => $receipt->dealer_id,
            'dealer_code' => $receipt->dealer?->code,
            'receipt_no' => $receipt->receipt_no,
            'document_no' => $receipt->document_no,
            'supplier_name' => $receipt->supplier_name,
            'warehouse_code' => $receipt->warehouse_code,
            'warehouse_name' => $receipt->warehouse_name,
            'received_at' => optional($receipt->received_at)?->toDateString(),
            'note' => $receipt->note,
            'status' => $receipt->status,
            'expected_quantity_total' => $expectedTotal,
            'accepted_quantity_total' => $acceptedTotal,
            'created_by_user_id' => $receipt->created_by,
            'created_by_name' => $receipt->createdBy?->name,
            'sync_status' => $state->status,
            'sync_error' => $state->last_error,
            'items' => $receipt->items->map(fn ($item): array => [
                'purchase_receipt_item_id' => $item->id,
                'product_code' => $item->product_code,
                'product_name' => $item->product_name,
                'expected_quantity' => (int) $item->expected_quantity,
                'accepted_quantity' => (int) $item->accepted_quantity,
                'note' => $item->note,
            ])->values()->all(),
            'logo' => [
                'document_type' => 'purchase_receipt',
                'document_label' => 'Satınalma Mal Kabul',
                'document_target' => 'purchase_dispatch_note',
                'target_tables' => ['STFICHE', 'STLINE'],
            ],
            'meta' => [
                'created_at' => optional($receipt->created_at)?->toIso8601String(),
                'updated_at' => optional($receipt->updated_at)?->toIso8601String(),
                'logo_external_ref' => $state->external_ref,
            ],
        ];
    }

    private function resolveDealer(mixed $dealerId, mixed $dealerCode): ?Dealer
    {
        if (is_numeric($dealerId)) {
            return Dealer::query()->find((int) $dealerId);
        }

        $code = trim((string) $dealerCode);
        if ($code !== '') {
            return Dealer::query()->where('code', $code)->first();
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
