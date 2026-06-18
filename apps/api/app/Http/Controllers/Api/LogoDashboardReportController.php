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
        $ledgerDateExpr = 'COALESCE(ledger_entries.`date`, ledger_entries.entry_date)';

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
        $collectionDateExpr = 'COALESCE(collections.`date`, collections.collection_date)';

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
        $driver = DB::connection()->getDriverName();
        $expression = $driver === 'sqlite'
            ? "(json_extract(products.meta, '$.integrations.logo.synced_at') IS NOT NULL OR json_extract(products.meta, '$.integrations.logo.external_ref') IS NOT NULL)"
            : "(JSON_EXTRACT(products.meta, '$.integrations.logo.synced_at') IS NOT NULL OR JSON_EXTRACT(products.meta, '$.integrations.logo.external_ref') IS NOT NULL)";

        $query->whereRaw($expression);
    }

    /**
     * @return array<int, array{domain:string,records:int,last_synced_at:?string}>
     */
    private function syncStates(?int $dealerId): array
    {
        return IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('direction', 'inbound')
            ->when($dealerId !== null, fn (EloquentBuilder $query) => $query->where('dealer_id', $dealerId))
            ->select('domain')
            ->selectRaw('COUNT(*) as records')
            ->selectRaw('MAX(last_synced_at) as last_synced_at')
            ->groupBy('domain')
            ->orderBy('domain')
            ->get()
            ->map(fn ($row): array => [
                'domain' => $row->domain,
                'records' => (int) $row->records,
                'last_synced_at' => $row->last_synced_at ? Carbon::parse($row->last_synced_at)->toJSON() : null,
            ])
            ->values()
            ->all();
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
