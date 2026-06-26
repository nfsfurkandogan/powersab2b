<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection as CollectionModel;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\IntegrationSyncState;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\StockSummary;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogoDashboardReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->hasRole('admin')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $dateFrom = Carbon::parse((string) $request->query('date_from', now()->subDays(30)->toDateString()))->toDateString();
        $dateTo = Carbon::parse((string) $request->query('date_to', now()->toDateString()))->toDateString();
        $dealerId = $this->resolveDealerId($request->query('dealer_id'));
        $ledgerDateExpr = $this->coalescedColumnExpression('ledger_entries.date', 'ledger_entries.entry_date');

        $logoCustomers = Customer::query()
            ->select(['id', 'dealer_id', 'salesperson_user_id', 'code', 'name', 'is_active', 'meta', 'last_synced_at'])
            ->where('source_system', 'logo')
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('dealer_id', $dealerId))
            ->with('salesperson:id,name,email,phone')
            ->get();

        $customerIds = $logoCustomers->pluck('id')->all();
        $ledgerBalances = $customerIds === []
            ? collect()
            : LedgerEntry::query()
                ->select('customer_id')
                ->selectRaw('COALESCE(SUM(COALESCE(debit, 0) - COALESCE(credit, 0)), 0) as balance')
                ->where('source_system', 'logo')
                ->whereIn('customer_id', $customerIds)
                ->whereRaw("DATE({$ledgerDateExpr}) <= ?", [$dateTo])
                ->groupBy('customer_id')
                ->pluck('balance', 'customer_id');

        $customerRows = $logoCustomers
            ->map(function (Customer $customer) use ($ledgerBalances): array {
                $ledgerBalance = (float) ($ledgerBalances->get($customer->id) ?? 0);
                $logoBalance = (float) data_get($customer->meta, 'integrations.logo.financials.total_due', 0);
                $balance = abs($ledgerBalance) > 0.004 ? $ledgerBalance : $logoBalance;

                return [
                    'customer_id' => (int) $customer->id,
                    'dealer_id' => (int) $customer->dealer_id,
                    'code' => $customer->code,
                    'title' => $customer->name,
                    'is_active' => (bool) $customer->is_active,
                    'card_type' => $this->logoCustomerCardType($customer),
                    'balance' => $balance,
                    'order_due' => (float) data_get($customer->meta, 'integrations.logo.financials.order_due', 0),
                    'last_synced_at' => $customer->last_synced_at?->toJSON(),
                    'salesperson' => [
                        'id' => $customer->salesperson?->id,
                        'name' => $customer->salesperson?->name ?? 'Atanmamış',
                        'email' => $customer->salesperson?->email,
                        'phone' => $customer->salesperson?->phone,
                    ],
                ];
            })
            ->values();

        $balanceTotal = $customerRows->sum('balance');
        $orderDueTotal = $customerRows->sum('order_due');
        $supplierRows = $customerRows
            ->filter(fn (array $row): bool => $this->isLogoSupplierCustomer($row))
            ->values();
        $supplierDebtTotal = $supplierRows->sum(fn (array $row): float => abs((float) $row['balance']));
        $salespersonBalanceSummary = $customerRows
            ->groupBy(fn (array $row): string => (string) ($row['salesperson']['id'] ?? 'unassigned'))
            ->map(function ($rows): array {
                $first = $rows->first();
                $salesperson = is_array($first) ? $first['salesperson'] : [];
                $balance = (float) $rows->sum('balance');
                $orderDue = (float) $rows->sum('order_due');

                return [
                    'salesperson' => [
                        'id' => $salesperson['id'] ?? null,
                        'name' => $salesperson['name'] ?? 'Atanmamış',
                        'email' => $salesperson['email'] ?? null,
                        'phone' => $salesperson['phone'] ?? null,
                    ],
                    'customer_count' => $rows->count(),
                    'active_customer_count' => $rows->where('is_active', true)->count(),
                    'balance_total' => $this->money($balance),
                    'order_due_total' => $this->money($orderDue),
                ];
            })
            ->sortByDesc(fn (array $row): float => abs((float) $row['balance_total']))
            ->values();

        $salesBase = LedgerEntry::query()
            ->where('ledger_entries.source_system', 'logo')
            ->where('ledger_entries.debit', '>', 0)
            ->whereIn('ledger_entries.type', ['invoice', 'debit'])
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('ledger_entries.dealer_id', $dealerId))
            ->whereRaw("DATE({$ledgerDateExpr}) >= ?", [$dateFrom])
            ->whereRaw("DATE({$ledgerDateExpr}) <= ?", [$dateTo]);

        $collectionsBase = LedgerEntry::query()
            ->where('ledger_entries.source_system', 'logo')
            ->where('ledger_entries.credit', '>', 0)
            ->whereIn('ledger_entries.type', ['payment', 'credit'])
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('ledger_entries.dealer_id', $dealerId))
            ->whereRaw("DATE({$ledgerDateExpr}) >= ?", [$dateFrom])
            ->whereRaw("DATE({$ledgerDateExpr}) <= ?", [$dateTo]);

        $salesSummary = (clone $salesBase)
            ->selectRaw('COUNT(*) as entry_count')
            ->selectRaw('COALESCE(SUM(ledger_entries.debit), 0) as total')
            ->first();

        $collectionsSummary = (clone $collectionsBase)
            ->selectRaw('COUNT(*) as entry_count')
            ->selectRaw('COALESCE(SUM(ledger_entries.credit), 0) as total')
            ->first();

        $dailyCollections = (clone $collectionsBase)
            ->reorder()
            ->selectRaw("DATE({$ledgerDateExpr}) as report_date")
            ->selectRaw('COUNT(*) as collection_count')
            ->selectRaw('COALESCE(SUM(ledger_entries.credit), 0) as total')
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get()
            ->map(fn ($row): array => [
                'date' => $row->report_date,
                'collection_count' => (int) $row->collection_count,
                'total' => $this->money($row->total),
            ])
            ->values();

        $dailySales = (clone $salesBase)
            ->reorder()
            ->selectRaw("DATE({$ledgerDateExpr}) as report_date")
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(ledger_entries.debit), 0) as total')
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get()
            ->map(fn ($row): array => [
                'date' => $row->report_date,
                'sales_count' => (int) $row->sales_count,
                'total' => $this->money($row->total),
            ])
            ->values();

        $salesBreakdown = (clone $salesBase)
            ->join('customers', 'customers.id', '=', 'ledger_entries.customer_id')
            ->reorder()
            ->select('ledger_entries.customer_id', 'customers.code as customer_code', 'customers.name as customer_title')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('COALESCE(SUM(ledger_entries.debit), 0) as net_total')
            ->groupBy('ledger_entries.customer_id', 'customers.code', 'customers.name')
            ->orderByDesc('net_total')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'customer' => [
                    'id' => (int) $row->customer_id,
                    'code' => $row->customer_code,
                    'title' => $row->customer_title,
                ],
                'order_count' => (int) $row->order_count,
                'quantity_total' => 0,
                'net_total' => $this->money($row->net_total),
                'tax_total' => $this->money(0),
            ])
            ->values();

        $recentMovements = LedgerEntry::query()
            ->where('ledger_entries.source_system', 'logo')
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('ledger_entries.dealer_id', $dealerId))
            ->whereRaw("DATE({$ledgerDateExpr}) >= ?", [$dateFrom])
            ->whereRaw("DATE({$ledgerDateExpr}) <= ?", [$dateTo])
            ->with(['customer:id,code,name'])
            ->reorder()
            ->orderByRaw("{$ledgerDateExpr} desc")
            ->orderByDesc('ledger_entries.id')
            ->limit(10)
            ->get()
            ->map(fn (LedgerEntry $entry): array => [
                'id' => (int) $entry->id,
                'date' => optional($entry->date ?? $entry->entry_date)?->toDateString(),
                'type' => $entry->type ?: $entry->entry_type,
                'direction' => (float) $entry->debit > 0 ? 'debit' : 'credit',
                'amount' => $this->money((float) $entry->debit > 0 ? $entry->debit : $entry->credit),
                'currency' => $entry->currency,
                'reference_no' => $entry->reference_no,
                'description' => $entry->description,
                'source_reference' => $entry->source_reference,
                'last_synced_at' => $entry->last_synced_at?->toJSON(),
                'customer' => [
                    'id' => $entry->customer?->id,
                    'code' => $entry->customer?->code,
                    'title' => $entry->customer?->name,
                ],
            ])
            ->values();

        $methodBreakdown = $this->collectionMethodBreakdown($dealerId, $dateFrom, $dateTo);
        $productCounts = $this->logoProductCounts();
        $syncStates = $this->syncStates($dealerId);
        $syncGaps = $this->syncGaps($dealerId, $productCounts);
        $lastSyncedAt = collect($syncStates)
            ->pluck('last_synced_at')
            ->filter()
            ->sort()
            ->last();

        return response()->json([
            'report' => 'logo_dashboard',
            'filters' => [
                'dealer_id' => $dealerId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'summary' => [
                'logo_customers_total' => $logoCustomers->count(),
                'logo_active_customers_total' => $customerRows->where('is_active', true)->count(),
                'logo_balance_total' => $this->money($balanceTotal),
                'logo_order_due_total' => $this->money($orderDueTotal),
                'logo_supplier_debt_total' => $this->money($supplierDebtTotal),
                'logo_suppliers_total' => $supplierRows->count(),
                'logo_collections_total' => $this->money($collectionsSummary?->total),
                'logo_collections_count' => (int) ($collectionsSummary?->entry_count ?? 0),
                'logo_sales_total' => $this->money($salesSummary?->total),
                'logo_sales_count' => (int) ($salesSummary?->entry_count ?? 0),
                'logo_products_total' => $productCounts['products_total'],
                'logo_stocked_products_total' => $productCounts['stocked_products_total'],
                'logo_last_synced_at' => $lastSyncedAt,
            ],
            'top_receivables' => $customerRows
                ->filter(fn (array $row): bool => $row['balance'] > 0)
                ->sortByDesc('balance')
                ->take(12)
                ->map(fn (array $row): array => [
                    'customer_id' => $row['customer_id'],
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'balance' => $this->money($row['balance']),
                ])
                ->values(),
            'salesperson_balance_summary' => $salespersonBalanceSummary,
            'supplier_debt_summary' => $supplierRows
                ->map(fn (array $row): array => [
                    'customer_id' => $row['customer_id'],
                    'code' => $row['code'],
                    'title' => $row['title'],
                    'balance' => $this->money($row['balance']),
                    'debt' => $this->money(abs((float) $row['balance'])),
                    'last_synced_at' => $row['last_synced_at'],
                ])
                ->sortByDesc(fn (array $row): float => (float) $row['debt'])
                ->take(8)
                ->values(),
            'method_breakdown' => $methodBreakdown,
            'daily_breakdown' => $dailyCollections,
            'daily_sales_breakdown' => $dailySales,
            'sales_breakdown' => $salesBreakdown,
            'recent_movements' => $recentMovements,
            'sync' => $syncStates,
            'sync_gaps' => $syncGaps,
            'market_rates' => $this->tcmbMarketRates(),
        ]);
    }

    private function resolveDealerId(mixed $dealerId): ?int
    {
        if ($dealerId === null || $dealerId === '') {
            return null;
        }

        $id = (int) $dealerId;

        return Dealer::query()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * @return array<int, array{method:string,collection_count:int,total:string}>
     */
    private function collectionMethodBreakdown(?int $dealerId, string $dateFrom, string $dateTo): array
    {
        $collectionDateExpr = $this->coalescedColumnExpression('collections.date', 'collections.collection_date');

        $rows = CollectionModel::query()
            ->where('source_system', 'logo')
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('dealer_id', $dealerId))
            ->whereRaw("DATE({$collectionDateExpr}) >= ?", [$dateFrom])
            ->whereRaw("DATE({$collectionDateExpr}) <= ?", [$dateTo])
            ->reorder()
            ->select('method')
            ->selectRaw('COUNT(*) as collection_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->keyBy('method');

        return collect(['cash', 'transfer', 'check', 'note', 'cc'])
            ->map(function (string $method) use ($rows): array {
                $row = $rows->get($method);

                return [
                    'method' => $method,
                    'collection_count' => (int) ($row?->collection_count ?? 0),
                    'total' => $this->money($row?->total),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{products_total:int,stocked_products_total:int}
     */
    private function logoProductCounts(): array
    {
        $productsQuery = Product::query();
        $this->applyLogoProductFilter($productsQuery);

        $stockQuery = StockSummary::query()
            ->join('products', 'products.id', '=', 'stock_summary.product_id')
            ->where('stock_summary.available_total', '>', 0);
        $this->applyLogoProductFilter($stockQuery);

        return [
            'products_total' => $productsQuery->count(),
            'stocked_products_total' => $stockQuery->count(),
        ];
    }

    private function applyLogoProductFilter(EloquentBuilder|QueryBuilder $query): void
    {
        $query->where(function (EloquentBuilder|QueryBuilder $query): void {
            $query
                ->whereNotNull('products.meta->integrations->logo->synced_at')
                ->orWhereNotNull('products.meta->integrations->logo->external_ref')
                ->orWhereNotNull('products.meta->integrations->logo->logical_ref');
        });
    }

    private function coalescedColumnExpression(string $primaryColumn, string $fallbackColumn): string
    {
        $grammar = DB::connection()->getQueryGrammar();

        return sprintf('COALESCE(%s, %s)', $grammar->wrap($primaryColumn), $grammar->wrap($fallbackColumn));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function syncStates(?int $dealerId): array
    {
        $rows = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('dealer_id', $dealerId))
            ->orderBy('domain')
            ->orderBy('direction')
            ->get(['domain', 'direction', 'status', 'last_error', 'last_synced_at', 'created_at', 'updated_at']);

        return $rows
            ->groupBy(fn (IntegrationSyncState $row): string => "{$row->domain}::{$row->direction}")
            ->map(function ($rows): array {
                $first = $rows->first();
                $sortedByActivity = $rows->sortByDesc(fn (IntegrationSyncState $row): int => $this->syncStateTimestamp($row))->values();
                $latest = $sortedByActivity->first();
                $lastSyncedAt = $rows
                    ->pluck('last_synced_at')
                    ->filter()
                    ->sortBy(fn ($value): int => Carbon::parse($value)->getTimestamp())
                    ->last();
                $lastError = $rows
                    ->filter(fn (IntegrationSyncState $row): bool => trim((string) $row->last_error) !== '')
                    ->sortByDesc(fn (IntegrationSyncState $row): int => $this->syncStateTimestamp($row))
                    ->first();
                $statusCounts = $rows
                    ->groupBy(fn (IntegrationSyncState $row): string => $this->normalizeSyncStatus($row->status))
                    ->map(fn ($group): int => $group->count())
                    ->all();

                $failedRecords = $rows->filter(function (IntegrationSyncState $row): bool {
                    $status = $this->normalizeSyncStatus($row->status);

                    return in_array($status, ['failed', 'error'], true) || trim((string) $row->last_error) !== '';
                })->count();

                $pendingRecords = $rows->filter(function (IntegrationSyncState $row): bool {
                    $status = $this->normalizeSyncStatus($row->status);

                    return in_array($status, ['pending', 'queued', 'processing'], true);
                })->count();
                $groupStatus = $failedRecords > 0
                    ? 'failed'
                    : ($pendingRecords > 0 ? 'pending' : $latest?->status);

                return [
                    'domain' => $first?->domain,
                    'direction' => $first?->direction,
                    'records' => $rows->count(),
                    'synced_records' => $rows->filter(fn (IntegrationSyncState $row): bool => $this->normalizeSyncStatus($row->status) === 'synced')->count(),
                    'failed_records' => $failedRecords,
                    'pending_records' => $pendingRecords,
                    'latest_status' => $this->normalizeSyncStatus($groupStatus),
                    'status_counts' => $statusCounts,
                    'last_synced_at' => $this->dateTimeJson($lastSyncedAt),
                    'last_activity_at' => $this->dateTimeJson($latest?->updated_at ?? $latest?->last_synced_at),
                    'last_error' => $lastError?->last_error,
                    'last_error_at' => $this->dateTimeJson($lastError?->updated_at ?? $lastError?->last_synced_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{products_total:int,stocked_products_total:int}  $productCounts
     * @return array<int, array<string, mixed>>
     */
    private function syncGaps(?int $dealerId, array $productCounts): array
    {
        $productSync = $this->syncStateSummary('products', 'inbound', Product::class, $dealerId);
        $productMissing = max(0, (int) $productCounts['products_total'] - $productSync['records']);
        $productStale = (int) $productCounts['products_total'] > 0 && $this->isSyncStale($productSync['last_synced_at'], 120) ? 1 : 0;
        $productShelfMissing = $this->countLogoProductsWithoutShelfAddress($dealerId);

        $ledgerExpected = $this->countRows('ledger_entries', $dealerId, function (QueryBuilder $query): void {
            $this->whereColumnIfExists($query, 'ledger_entries', 'source_system', 'logo');
        });
        $ledgerSync = $this->syncStateSummary('ledger', 'inbound', LedgerEntry::class, $dealerId);
        $ledgerMissing = $this->countMissingSyncStates('ledger_entries', 'ledger', 'inbound', LedgerEntry::class, $dealerId, function (QueryBuilder $query): void {
            $this->whereColumnIfExists($query, 'ledger_entries', 'source_system', 'logo');
        });
        $ledgerStale = $ledgerExpected > 0 && $this->isSyncStale($ledgerSync['last_synced_at'], 1440) ? 1 : 0;

        $collectionExpected = $this->countRows('collections', $dealerId, function (QueryBuilder $query): void {
            if (Schema::hasColumn('collections', 'sync_status')) {
                $query->where(function (QueryBuilder $query): void {
                    $query->whereNull('source.sync_status')->orWhere('source.sync_status', '<>', 'draft');
                });
            }
        });
        $collectionSync = $this->syncStateSummary('collections', 'outbound', CollectionModel::class, $dealerId);
        $collectionMissing = $this->countMissingSyncStates('collections', 'collections', 'outbound', CollectionModel::class, $dealerId, function (QueryBuilder $query): void {
            if (Schema::hasColumn('collections', 'sync_status')) {
                $query->where(function (QueryBuilder $query): void {
                    $query->whereNull('source.sync_status')->orWhere('source.sync_status', '<>', 'draft');
                });
            }
        });

        $posExpected = $this->countRows('pos_sales', $dealerId, function (QueryBuilder $query): void {
            if (Schema::hasColumn('pos_sales', 'status')) {
                $query->where('source.status', '<>', 'draft');
            }
        });
        $posSync = $this->syncStateSummary('pos-sales', 'outbound', 'App\\Models\\PosSale', $dealerId);
        $posMissing = $this->countMissingSyncStates('pos_sales', 'pos-sales', 'outbound', 'App\\Models\\PosSale', $dealerId, function (QueryBuilder $query): void {
            if (Schema::hasColumn('pos_sales', 'status')) {
                $query->where('source.status', '<>', 'draft');
            }
        });

        $orderExpected = $this->countRows('orders', $dealerId, function (QueryBuilder $query): void {
            if (Schema::hasColumn('orders', 'status')) {
                $query->where('source.status', '<>', 'draft');
            }
        });
        $orderSync = $this->syncStateSummary('orders', 'outbound', 'App\\Models\\Order', $dealerId);
        $orderMissing = $this->countMissingSyncStates('orders', 'orders', 'outbound', 'App\\Models\\Order', $dealerId, function (QueryBuilder $query): void {
            if (Schema::hasColumn('orders', 'status')) {
                $query->where('source.status', '<>', 'draft');
            }
        });

        $shipmentExpected = $this->countRows('shipments', $dealerId);
        $shipmentSync = $this->syncStateSummary('warehouse-shipments', 'outbound', 'App\\Models\\Shipment', $dealerId);
        $shipmentMissing = $this->countMissingSyncStates('shipments', 'warehouse-shipments', 'outbound', 'App\\Models\\Shipment', $dealerId);

        $returnExpected = $this->countRows('return_requests', $dealerId);
        $returnSync = $this->syncStateSummary('returns', 'outbound', 'App\\Models\\ReturnRequest', $dealerId);
        $returnMissing = $this->countMissingSyncStates('return_requests', 'returns', 'outbound', 'App\\Models\\ReturnRequest', $dealerId);

        return collect([
            $this->syncGapRow(
                key: 'products-inbound',
                title: 'Ürün okuma',
                flow: 'Logo -> B2B',
                status: $this->syncGapStatus($productMissing, $productSync['failed'], $productStale),
                expectedCount: (int) $productCounts['products_total'],
                syncedCount: $productSync['records'],
                missingCount: $productMissing,
                staleCount: $productStale,
                lastSyncedAt: $productSync['last_synced_at'],
                lastActivityAt: $productSync['last_activity_at'],
                detail: $productMissing > 0
                    ? 'Logo ürün kaydı B2B sync state tablosunda eksik görünüyor.'
                    : ($productStale > 0 ? 'Ürün sync zamanı iki saati geçti; hızlı ürün görevi kontrol edilmeli.' : 'Ürün okuma kayıtları tamam.')
            ),
            $this->syncGapRow(
                key: 'products-shelf',
                title: 'Ürün raf adresleri',
                flow: 'Logo -> B2B',
                status: $productShelfMissing > 0 ? 'warning' : 'ok',
                expectedCount: (int) $productCounts['products_total'],
                syncedCount: max(0, (int) $productCounts['products_total'] - $productShelfMissing),
                missingCount: $productShelfMissing,
                staleCount: 0,
                lastSyncedAt: $productSync['last_synced_at'],
                lastActivityAt: $productSync['last_activity_at'],
                detail: $productShelfMissing > 0
                    ? 'Logo raf kaynağı veya raf map dosyası eksik; ürün listesinde raf adresi boş kalır.'
                    : 'Raf adresleri Logo payload içinde görünüyor.'
            ),
            $this->syncGapRow(
                key: 'ledger-inbound',
                title: 'Cari hesap hareketleri',
                flow: 'Logo -> B2B',
                status: $this->syncGapStatus($ledgerMissing, $ledgerSync['failed'], $ledgerStale, true),
                expectedCount: $ledgerExpected,
                syncedCount: $ledgerSync['records'],
                missingCount: $ledgerMissing,
                staleCount: $ledgerStale,
                lastSyncedAt: $ledgerSync['last_synced_at'],
                lastActivityAt: $ledgerSync['last_activity_at'],
                detail: $ledgerMissing > 0
                    ? 'Ledger kayıtları ile Logo sync state sayısı eşleşmiyor.'
                    : ($ledgerStale > 0 ? 'Cari hareket sync zamanı bir günü geçti.' : 'Cari hareket okuma kayıtları tamam.')
            ),
            $this->syncGapRow(
                key: 'collections-outbound',
                title: 'Tahsilat yazma',
                flow: 'B2B -> Logo',
                status: $this->syncGapStatus($collectionMissing, $collectionSync['failed'], 0),
                expectedCount: $collectionExpected,
                syncedCount: $collectionSync['records'],
                missingCount: $collectionMissing,
                staleCount: 0,
                lastSyncedAt: $collectionSync['last_synced_at'],
                lastActivityAt: $collectionSync['last_activity_at'],
                detail: $collectionMissing > 0 ? 'Taslak olmayan tahsilatlarda Logo yazma state eksik.' : 'Tahsilat yazma kayıtları tamam.'
            ),
            $this->syncGapRow(
                key: 'pos-sales-outbound',
                title: 'POS satış yazma',
                flow: 'B2B -> Logo',
                status: $this->syncGapStatus($posMissing, $posSync['failed'], 0),
                expectedCount: $posExpected,
                syncedCount: $posSync['records'],
                missingCount: $posMissing,
                staleCount: 0,
                lastSyncedAt: $posSync['last_synced_at'],
                lastActivityAt: $posSync['last_activity_at'],
                detail: $posMissing > 0 ? 'POS satışlarda Logo yazma state eksik.' : 'POS satış yazma kayıtları tamam.'
            ),
            $this->syncGapRow(
                key: 'orders-outbound',
                title: 'Sipariş yazma',
                flow: 'B2B -> Logo',
                status: $this->syncGapStatus($orderMissing, $orderSync['failed'], 0),
                expectedCount: $orderExpected,
                syncedCount: $orderSync['records'],
                missingCount: $orderMissing,
                staleCount: 0,
                lastSyncedAt: $orderSync['last_synced_at'],
                lastActivityAt: $orderSync['last_activity_at'],
                detail: $orderMissing > 0 ? 'Siparişlerde Logo yazma state eksik.' : 'Sipariş yazma kayıtları tamam.'
            ),
            $this->syncGapRow(
                key: 'warehouse-shipments-outbound',
                title: 'Depo sevkiyat yazma',
                flow: 'B2B -> Logo',
                status: $this->syncGapStatus($shipmentMissing, $shipmentSync['failed'], 0),
                expectedCount: $shipmentExpected,
                syncedCount: $shipmentSync['records'],
                missingCount: $shipmentMissing,
                staleCount: 0,
                lastSyncedAt: $shipmentSync['last_synced_at'],
                lastActivityAt: $shipmentSync['last_activity_at'],
                detail: $shipmentMissing > 0 ? 'Depo sevkiyatında Logo yazma state eksik; paketleme/irsaliye akışı kontrol edilmeli.' : 'Depo sevkiyat yazma kayıtları tamam.'
            ),
            $this->syncGapRow(
                key: 'returns-outbound',
                title: 'İade yazma',
                flow: 'B2B -> Logo',
                status: $this->syncGapStatus($returnMissing, $returnSync['failed'], 0),
                expectedCount: $returnExpected,
                syncedCount: $returnSync['records'],
                missingCount: $returnMissing,
                staleCount: 0,
                lastSyncedAt: $returnSync['last_synced_at'],
                lastActivityAt: $returnSync['last_activity_at'],
                detail: $returnMissing > 0 ? 'İade kayıtlarında Logo yazma state eksik.' : 'İade yazma kayıtları tamam.'
            ),
        ])
            ->sortBy(fn (array $row): array => [$this->syncGapSeverityScore((string) $row['status']), $row['title']])
            ->values()
            ->all();
    }

    private function syncStateSummary(string $domain, string $direction, string $entityType, ?int $dealerId): array
    {
        $rows = IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', $domain)
            ->where('direction', $direction)
            ->where('entity_type', $entityType)
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('dealer_id', $dealerId))
            ->get(['status', 'last_error', 'last_synced_at', 'created_at', 'updated_at']);

        $failed = $rows->filter(function (IntegrationSyncState $row): bool {
            $status = $this->normalizeSyncStatus($row->status);

            return in_array($status, ['failed', 'error'], true) || trim((string) $row->last_error) !== '';
        })->count();

        $lastSyncedAt = $rows
            ->pluck('last_synced_at')
            ->filter()
            ->sortBy(fn ($value): int => Carbon::parse($value)->getTimestamp())
            ->last();
        $latest = $rows
            ->sortByDesc(fn (IntegrationSyncState $row): int => $this->syncStateTimestamp($row))
            ->first();

        return [
            'records' => $rows->count(),
            'failed' => $failed,
            'last_synced_at' => $lastSyncedAt,
            'last_activity_at' => $latest?->updated_at ?? $latest?->last_synced_at,
        ];
    }

    private function syncGapRow(
        string $key,
        string $title,
        string $flow,
        string $status,
        int $expectedCount,
        int $syncedCount,
        int $missingCount,
        int $staleCount,
        mixed $lastSyncedAt,
        mixed $lastActivityAt,
        string $detail,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'flow' => $flow,
            'status' => $status,
            'expected_count' => max(0, $expectedCount),
            'synced_count' => max(0, $syncedCount),
            'missing_count' => max(0, $missingCount),
            'stale_count' => max(0, $staleCount),
            'last_synced_at' => $this->dateTimeJson($lastSyncedAt),
            'last_activity_at' => $this->dateTimeJson($lastActivityAt),
            'detail' => $detail,
        ];
    }

    private function syncGapStatus(int $missingCount, int $failedCount, int $staleCount, bool $staleIsCritical = false): string
    {
        if ($failedCount > 0 || ($staleIsCritical && $staleCount > 0)) {
            return 'critical';
        }

        if ($missingCount > 0 || $staleCount > 0) {
            return 'warning';
        }

        return 'ok';
    }

    private function syncGapSeverityScore(string $status): int
    {
        return match ($status) {
            'critical' => 0,
            'warning' => 1,
            'ok' => 2,
            default => 3,
        };
    }

    private function isSyncStale(mixed $value, int $minutes): bool
    {
        if (! $value) {
            return true;
        }

        return Carbon::parse($value)->lt(now()->subMinutes($minutes));
    }

    private function countRows(string $table, ?int $dealerId = null, ?callable $filter = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        try {
            $query = DB::table("{$table} as source");
            $this->applyDealerFilter($query, $table, $dealerId);

            if ($filter) {
                $filter($query);
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countMissingSyncStates(string $table, string $domain, string $direction, string $entityType, ?int $dealerId = null, ?callable $filter = null): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable('integration_sync_states')) {
            return 0;
        }

        try {
            $query = DB::table("{$table} as source")
                ->leftJoin('integration_sync_states as state', function ($join) use ($domain, $direction, $entityType): void {
                    $join->on('state.entity_id', '=', 'source.id')
                        ->where('state.system', '=', 'logo')
                        ->where('state.domain', '=', $domain)
                        ->where('state.direction', '=', $direction)
                        ->where('state.entity_type', '=', $entityType);
                })
                ->whereNull('state.id');
            $this->applyDealerFilter($query, $table, $dealerId);

            if ($filter) {
                $filter($query);
            }

            return (int) $query->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function applyDealerFilter(QueryBuilder $query, string $table, ?int $dealerId): void
    {
        if ($dealerId !== null && Schema::hasColumn($table, 'dealer_id')) {
            $query->where('source.dealer_id', $dealerId);
        }
    }

    private function whereColumnIfExists(QueryBuilder $query, string $table, string $column, mixed $value): void
    {
        if (Schema::hasColumn($table, $column)) {
            $query->where("source.{$column}", $value);
        }
    }

    private function countLogoProductsWithoutShelfAddress(?int $dealerId): int
    {
        if (! Schema::hasTable('products')) {
            return 0;
        }

        $query = Product::query()->select(['id', 'meta']);
        $this->applyLogoProductFilter($query);

        if ($dealerId !== null && Schema::hasColumn('products', 'dealer_id')) {
            $query->where('products.dealer_id', $dealerId);
        }

        $scopeQuery = clone $query;
        $latestUpdatedAt = (string) ($scopeQuery->toBase()->max('products.updated_at') ?? '');
        $totalProducts = (clone $query)->count();
        $cacheKey = sprintf(
            'logo-dashboard:missing-product-shelf:v3:%s:%d:%s',
            $dealerId ?? 'all',
            $totalProducts,
            md5($latestUpdatedAt)
        );

        return (int) Cache::remember($cacheKey, now()->addMinutes(2), function () use ($query, $totalProducts): int {
            $count = 0;

            try {
                $query->chunkById(500, function ($products) use (&$count): void {
                    foreach ($products as $product) {
                        if (! $this->metaHasShelfAddress($product->meta)) {
                            $count++;
                        }
                    }
                }, 'id');
            } catch (Throwable) {
                return (int) $totalProducts;
            }

            return $count;
        });
    }

    private function metaHasShelfAddress(mixed $meta): bool
    {
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($meta)) {
            return false;
        }

        foreach ([
            'integrations.logo.payload.shelf_address',
            'integrations.logo.payload.raf',
            'integrations.logo.payload.raf_adresi',
            'integrations.logo.payload.logo_stock.shelf_address',
        ] as $path) {
            if ($this->nonBlankString(data_get($meta, $path)) !== null) {
                return true;
            }
        }

        $warehouses = data_get($meta, 'integrations.logo.payload.logo_stock.warehouses');
        if (is_array($warehouses)) {
            foreach ($warehouses as $warehouse) {
                if (is_array($warehouse) && $this->nonBlankString(data_get($warehouse, 'shelf_address')) !== null) {
                    return true;
                }
            }
        }

        $raw = data_get($meta, 'integrations.logo.payload.raw');
        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if ($this->isShelfAddressKey((string) $key) && $this->nonBlankString($value) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isShelfAddressKey(string $key): bool
    {
        $normalized = strtoupper(str_replace([' ', '-', '.'], '_', $key));

        return preg_match(
            '/^(RAF(_?\d+)?|RAF_?ADRESI(_?\d+)?|RAF_?BILGISI(_?\d+)?|RAF_?BILGILERI(_?\d+)?|SHELF_?ADDRESS(_?\d+)?|LOCATION(_?CODE)?(_?\d+)?)$/',
            $normalized
        ) === 1;
    }

    private function nonBlankString(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' && $value !== '-' && preg_match('/^0+$/', $value) !== 1 ? $value : null;
    }

    private function normalizeSyncStatus(mixed $status): string
    {
        $value = strtolower(trim((string) $status));

        return $value !== '' ? $value : 'unknown';
    }

    private function syncStateTimestamp(IntegrationSyncState $row): int
    {
        return collect([$row->updated_at, $row->last_synced_at, $row->created_at])
            ->filter()
            ->map(fn ($value): int => Carbon::parse($value)->getTimestamp())
            ->max() ?? 0;
    }

    private function dateTimeJson(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toJSON() : null;
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function logoCustomerCardType(Customer $customer): ?int
    {
        $cardType = data_get($customer->meta, 'integrations.logo.payload.cardtype')
            ?? data_get($customer->meta, 'integrations.logo.payload.CARDTYPE')
            ?? data_get($customer->meta, 'integrations.logo.payload.raw.CARDTYPE');

        return is_numeric($cardType) ? (int) $cardType : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isLogoSupplierCustomer(array $row): bool
    {
        $code = preg_replace('/\D+/', '', (string) ($row['code'] ?? '')) ?? '';
        $cardType = is_numeric($row['card_type'] ?? null) ? (int) $row['card_type'] : null;

        return str_starts_with($code, '320') || in_array($cardType, [2, 22], true);
    }

    /**
     * @return array{source:string,source_url:string,source_date:?string,updated_at:string,rates:array<int,array<string,mixed>>}
     */
    private function tcmbMarketRates(): array
    {
        return Cache::remember('tcmb_market_rates_today_xml', now()->addMinutes(30), function (): array {
            $sourceUrl = 'https://www.tcmb.gov.tr/kurlar/today.xml';
            $fallback = [
                'source' => 'TCMB',
                'source_url' => $sourceUrl,
                'source_date' => null,
                'updated_at' => now()->toJSON(),
                'rates' => [
                    $this->unavailableRate('USD', 'Dolar'),
                    $this->unavailableRate('EUR', 'Euro'),
                    $this->unavailableRate('XAU', 'Altın', 'TCMB today.xml içinde altın kuru yok.'),
                ],
            ];

            try {
                $response = Http::timeout(6)->retry(1, 250)->get($sourceUrl);

                if (! $response->successful()) {
                    return $fallback;
                }

                $xml = simplexml_load_string($response->body());

                if ($xml === false) {
                    return $fallback;
                }

                $usd = $this->tcmbRateFromXml($xml, 'USD', 'Dolar');
                $eur = $this->tcmbRateFromXml($xml, 'EUR', 'Euro');
                $gold = $this->tcmbRateFromXml($xml, 'XAU', 'Altın')
                    ?? $this->tcmbRateFromXml($xml, 'GAU', 'Gram Altın')
                    ?? $this->unavailableRate('XAU', 'Altın', 'TCMB today.xml içinde altın kuru yok.');

                return [
                    'source' => 'TCMB',
                    'source_url' => $sourceUrl,
                    'source_date' => (string) ($xml['Tarih'] ?? $xml['Date'] ?? ''),
                    'updated_at' => now()->toJSON(),
                    'rates' => [
                        $usd ?? $fallback['rates'][0],
                        $eur ?? $fallback['rates'][1],
                        $gold,
                    ],
                ];
            } catch (Throwable) {
                return $fallback;
            }
        });
    }

    /**
     * @return array<string,mixed>|null
     */
    private function tcmbRateFromXml(\SimpleXMLElement $xml, string $code, string $label): ?array
    {
        $nodes = $xml->xpath("//Currency[@Kod='{$code}']");

        if (! is_array($nodes) || ! isset($nodes[0])) {
            return null;
        }

        $node = $nodes[0];

        return [
            'code' => $code,
            'label' => $label,
            'unit' => (int) ((string) ($node->Unit ?? '1') ?: 1),
            'name' => (string) ($node->Isim ?? $label),
            'forex_buying' => $this->nullableMoney($node->ForexBuying ?? null),
            'forex_selling' => $this->nullableMoney($node->ForexSelling ?? null),
            'banknote_buying' => $this->nullableMoney($node->BanknoteBuying ?? null),
            'banknote_selling' => $this->nullableMoney($node->BanknoteSelling ?? null),
            'available' => true,
            'note' => null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function unavailableRate(string $code, string $label, ?string $note = null): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'unit' => 1,
            'name' => $label,
            'forex_buying' => null,
            'forex_selling' => null,
            'banknote_buying' => null,
            'banknote_selling' => null,
            'available' => false,
            'note' => $note ?? 'Kur bilgisi alınamadı.',
        ];
    }

    private function nullableMoney(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return number_format((float) $raw, 4, '.', '');
    }
}
