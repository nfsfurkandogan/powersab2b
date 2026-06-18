<?php

namespace App\Services\Integrations\Logo;

use App\Models\BasePrice;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCodeAlias;
use App\Models\StockSummary;
use App\Services\Integrations\IntegrationSyncStateService;
use App\Services\Meilisearch\ProductSearchService;
use App\Support\Products\ProductCodeNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogoProductSyncService
{
    /**
     * @var list<string>
     */
    private const LOGO_RAW_CODE_ALIAS_PATHS = [
        'meta.raw.CODE',
        'meta.raw.CODE_',
        'meta.raw.ITEMCODE',
        'meta.raw.ITEM_CODE',
        'meta.raw.STOKKODU',
        'meta.raw.STOK_KODU',
        'meta.raw.PRODUCERCODE',
        'meta.raw.PRODUCER_CODE',
        'meta.raw.OEMCODE',
        'meta.raw.OEM_CODE',
        'meta.raw.BARCODE',
        'meta.raw.BARCODE1',
        'meta.raw.EAN13',
    ];

    public function __construct(
        private readonly ProductSearchService $productSearch,
        private readonly IntegrationSyncStateService $syncState
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public function sync(array $payload): array
    {
        $summary = [
            'received' => count($payload['records'] ?? []),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'brands_created' => 0,
            'brands_updated' => 0,
            'categories_created' => 0,
            'categories_updated' => 0,
            'stock_synced' => 0,
            'prices_synced' => 0,
            'code_aliases_synced' => 0,
        ];

        $defaultPriceList = $this->resolvePriceList(
            $payload['price_list_id'] ?? null,
            $payload['price_list_code'] ?? null,
        );

        if ($this->isStockOnlyPayload($payload)) {
            return $this->syncStockOnly($payload, $summary);
        }

        $indexedProductIds = [];

        DB::transaction(function () use ($payload, $defaultPriceList, &$summary, &$indexedProductIds): void {
            foreach ((array) ($payload['records'] ?? []) as $index => $record) {
                $brand = $this->resolveBrand($record, $summary);
                $category = $this->resolveCategory($record, $summary);

                $externalReference = $this->nullableString($record['external_ref'] ?? null);
                $product = $this->findProduct((string) $record['sku'], $externalReference);

                $attributes = [
                    'brand_id' => $brand?->id,
                    'category_id' => $category?->id,
                    'sku' => (string) $record['sku'],
                    'oem_code' => $this->nullableString($record['oem_code'] ?? ($product?->oem_code)),
                    'name' => $this->resolveProductName($record, (string) $record['sku']),
                    'description' => $this->resolveProductDescription($record, $product?->description),
                    'unit' => $this->nullableString($record['unit'] ?? ($product?->unit)) ?? 'adet',
                    'vat_rate' => array_key_exists('vat_rate', $record)
                        ? $record['vat_rate']
                        : ($product?->vat_rate ?? 20),
                    'weight_kg' => array_key_exists('weight_kg', $record)
                        ? $record['weight_kg']
                        : $product?->weight_kg,
                    'is_active' => array_key_exists('is_active', $record)
                        ? (bool) $record['is_active']
                        : ($product?->is_active ?? true),
                    'meta' => $this->buildMeta($product, $record, $externalReference),
                ];

                $product = Product::withoutEvents(function () use ($product, $attributes, &$summary): Product {
                    if ($product) {
                        $product->fill($attributes)->save();
                        $summary['updated']++;

                        return $product;
                    }

                    $summary['created']++;

                    return Product::query()->create($attributes);
                });

                $indexedProductIds[(int) $product->id] = true;
                $this->syncState->record(
                    system: 'logo',
                    domain: 'products',
                    direction: 'inbound',
                    entity: $product,
                    externalRef: $externalReference,
                    status: 'synced',
                    meta: [
                        'operation' => $product->wasRecentlyCreated ? 'created' : 'updated',
                        'stock_synced' => array_key_exists('available_total', $record) || array_key_exists('reserved_total', $record),
                        'price_synced' => array_key_exists('list_price', $record),
                    ],
                    payload: $record,
                );

                if ($this->syncStockSummary($product, $record)) {
                    $summary['stock_synced']++;
                }

                if ($this->syncBasePrice($product, $record, $defaultPriceList, $index)) {
                    $summary['prices_synced']++;
                }

                $summary['code_aliases_synced'] += $this->syncCodeAliases($product, $record);
            }
        });

        $this->reindexProducts(array_keys($indexedProductIds));

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isStockOnlyPayload(array $payload): bool
    {
        return ($payload['mode'] ?? null) === 'stock_only'
            || filter_var($payload['stock_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $summary
     * @return array<string, int>
     */
    private function syncStockOnly(array $payload, array $summary): array
    {
        $indexedProductIds = [];

        DB::transaction(function () use ($payload, &$summary, &$indexedProductIds): void {
            foreach ((array) ($payload['records'] ?? []) as $record) {
                if (! is_array($record)) {
                    $summary['skipped']++;

                    continue;
                }

                $externalReference = $this->nullableString($record['external_ref'] ?? null);
                $sku = $this->nullableString($record['sku'] ?? null);

                if ($externalReference === null && $sku === null) {
                    $summary['skipped']++;

                    continue;
                }

                $product = $this->findProduct($sku ?? '', $externalReference);
                if (! $product) {
                    $summary['skipped']++;

                    continue;
                }

                if ($this->syncStockSummary($product, $record)) {
                    $summary['stock_synced']++;
                }

                $product = Product::withoutEvents(function () use ($product, $record, $externalReference): Product {
                    $product->forceFill([
                        'meta' => $this->buildStockOnlyMeta($product, $record, $externalReference),
                    ])->save();

                    return $product;
                });

                $summary['updated']++;
                $indexedProductIds[(int) $product->id] = true;

                $this->syncState->record(
                    system: 'logo',
                    domain: 'products',
                    direction: 'inbound',
                    entity: $product,
                    externalRef: $externalReference,
                    status: 'synced',
                    meta: [
                        'operation' => 'stock_only',
                        'stock_synced' => array_key_exists('available_total', $record) || array_key_exists('reserved_total', $record),
                        'price_synced' => false,
                    ],
                    payload: $record,
                );
            }
        });

        $this->reindexProducts(array_keys($indexedProductIds));

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, int>  $summary
     */
    private function resolveBrand(array $record, array &$summary): ?Brand
    {
        $specode5 = $this->nullableString($record['specode5'] ?? null)
            ?? $this->nullableString(data_get($record, 'meta.specode5'))
            ?? $this->nullableString(data_get($record, 'meta.raw.SPECODE5'));

        $hasBrandContext = $specode5 !== null
            || array_key_exists('brand_name', $record)
            || array_key_exists('brand_code', $record);
        if (! $hasBrandContext) {
            return null;
        }

        $code = $specode5 ?? $this->nullableString($record['brand_code'] ?? null);
        $name = $specode5 ?? $this->nullableString($record['brand_name'] ?? null) ?? $code;
        if ($name === null) {
            return null;
        }

        $slug = $this->stableSlug($code, $name, 'brand');
        $brand = Brand::query()
            ->where('slug', $slug)
            ->first();

        if (! $brand) {
            $brand = Brand::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
        }

        $attributes = [
            'name' => $name,
            'slug' => $slug,
            'is_active' => array_key_exists('brand_is_active', $record)
                ? (bool) $record['brand_is_active']
                : ($brand?->is_active ?? true),
        ];

        if ($brand) {
            if ($this->fillAndSave($brand, $attributes)) {
                $summary['brands_updated']++;
            }

            return $brand;
        }

        $summary['brands_created']++;

        return Brand::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, int>  $summary
     */
    private function resolveCategory(array $record, array &$summary): ?Category
    {
        $hasCategoryContext = array_key_exists('category_name', $record) || array_key_exists('category_code', $record);
        if (! $hasCategoryContext) {
            return null;
        }

        $code = $this->nullableString($record['category_code'] ?? null);
        $name = $this->nullableString($record['category_name'] ?? null) ?? $code;
        if ($name === null) {
            return null;
        }

        $slug = $this->stableSlug($code, $name, 'category');
        $category = Category::query()
            ->where('slug', $slug)
            ->first();

        if (! $category) {
            $category = Category::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->whereNull('parent_id')
                ->first();
        }

        $attributes = [
            'name' => $name,
            'slug' => $slug,
            'is_active' => array_key_exists('category_is_active', $record)
                ? (bool) $record['category_is_active']
                : ($category?->is_active ?? true),
        ];

        if ($category) {
            if ($this->fillAndSave($category, $attributes)) {
                $summary['categories_updated']++;
            }

            return $category;
        }

        $summary['categories_created']++;

        return Category::query()->create($attributes);
    }

    private function findProduct(string $sku, ?string $externalReference): ?Product
    {
        if ($externalReference !== null) {
            $query = Product::query();
            $driver = DB::connection()->getDriverName();

            $byExternalReference = $driver === 'sqlite'
                ? $query->whereRaw("json_extract(meta, '$.integrations.logo.external_ref') = ?", [$externalReference])->first()
                : $query->where('meta->integrations->logo->external_ref', $externalReference)->first();

            if ($byExternalReference) {
                return $byExternalReference;
            }
        }

        return Product::query()
            ->where('sku', $sku)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function syncStockSummary(Product $product, array $record): bool
    {
        $hasStockData = array_key_exists('available_total', $record) || array_key_exists('reserved_total', $record);
        if (! $hasStockData) {
            return false;
        }

        if ($this->looksLikeEmptyLogoStockSnapshot($record)) {
            return false;
        }

        $stockSummary = StockSummary::query()->find($product->id);
        $availableTotal = array_key_exists('available_total', $record)
            ? (int) ($record['available_total'] ?? 0)
            : ($stockSummary?->available_total ?? 0);
        $reservedTotal = array_key_exists('reserved_total', $record)
            ? max(0, (int) ($record['reserved_total'] ?? 0))
            : ($stockSummary?->reserved_total ?? 0);

        $attributes = [
            'available_total' => $availableTotal,
            'reserved_total' => $reservedTotal,
            'updated_at' => now(),
        ];

        if ($stockSummary) {
            $stockSummary->fill($attributes)->save();

            return true;
        }

        StockSummary::query()->create([
            'product_id' => $product->id,
            ...$attributes,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function looksLikeEmptyLogoStockSnapshot(array $record): bool
    {
        if ((int) ($record['available_total'] ?? 0) !== 0 || (int) ($record['reserved_total'] ?? 0) !== 0) {
            return false;
        }

        $logoStock = data_get($record, 'meta.logo_stock');
        if (! is_array($logoStock)) {
            return false;
        }

        return array_key_exists('warehouses', $logoStock) && count((array) $logoStock['warehouses']) === 0;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function syncCodeAliases(Product $product, array $record): int
    {
        $hasAuthoritativeAliases = array_key_exists('code_aliases', $record);
        $rawCodeAliases = $this->extractLogoRawCodeAliases($record);
        $barcodeAliases = $this->extractLogoUnitBarcodeAliases($record);

        if (! $hasAuthoritativeAliases && $rawCodeAliases === [] && $barcodeAliases === []) {
            return 0;
        }

        $normalizedAliases = collect([
            ...($hasAuthoritativeAliases ? (array) ($record['code_aliases'] ?? []) : []),
            ...$rawCodeAliases,
            ...$barcodeAliases,
        ])
            ->map(fn ($alias) => $this->normalizeCodeAlias($alias))
            ->filter()
            ->values();

        $incomingKeys = $normalizedAliases
            ->map(fn (array $alias) => $this->codeAliasUniqueKey($alias))
            ->all();

        if ($hasAuthoritativeAliases) {
            ProductCodeAlias::query()
                ->where('product_id', $product->id)
                ->where('source', 'logo')
                ->get()
                ->each(function (ProductCodeAlias $alias) use ($incomingKeys): void {
                    $currentKey = $this->codeAliasUniqueKey([
                        'normalized_code' => $alias->normalized_code,
                        'code_type' => $alias->code_type,
                        'source' => $alias->source,
                    ]);

                    if (! in_array($currentKey, $incomingKeys, true)) {
                        $alias->delete();
                    }
                });
        }

        foreach ($normalizedAliases as $alias) {
            ProductCodeAlias::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'normalized_code' => $alias['normalized_code'],
                    'code_type' => $alias['code_type'],
                    'source' => $alias['source'],
                ],
                [
                    'code' => $alias['code'],
                    'brand_name' => $alias['brand_name'],
                    'meta' => $alias['meta'],
                ]
            );
        }

        return $normalizedAliases->count();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<array<string, mixed>>
     */
    private function extractLogoRawCodeAliases(array $record): array
    {
        $aliases = [];
        $seen = [];

        foreach (self::LOGO_RAW_CODE_ALIAS_PATHS as $path) {
            $code = $this->nullableString(data_get($record, $path));
            $normalized = ProductCodeNormalizer::normalize($code);

            if ($code === null || $normalized === null || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $aliases[] = [
                'code' => $code,
                'type' => 'logo_code',
                'brand_name' => null,
                'meta' => [
                    'source' => 'logo_raw_code',
                    'path' => $path,
                ],
            ];
        }

        return $aliases;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<array<string, mixed>>
     */
    private function extractLogoUnitBarcodeAliases(array $record): array
    {
        $aliases = [];
        $seen = [];
        $units = data_get($record, 'meta.logo_units.units', []);

        if (! is_array($units)) {
            return [];
        }

        foreach ($units as $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $barcode = $this->nullableString($unit['barcode'] ?? null);
            $normalized = ProductCodeNormalizer::normalize($barcode);

            if ($barcode === null || $normalized === null || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $aliases[] = [
                'code' => $barcode,
                'type' => 'other',
                'brand_name' => null,
                'meta' => [
                    'source' => 'logo_unit_barcode',
                    'unit_code' => $this->nullableString($unit['code'] ?? null),
                    'unit_name' => $this->nullableString($unit['name'] ?? null),
                ],
            ];
        }

        return $aliases;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function syncBasePrice(Product $product, array $record, ?PriceList $defaultPriceList, int $index): bool
    {
        if (! array_key_exists('list_price', $record)) {
            return false;
        }

        $priceList = $this->resolvePriceList(
            $record['price_list_id'] ?? null,
            $record['price_list_code'] ?? null,
            $defaultPriceList,
        );

        if (! $priceList) {
            throw ValidationException::withMessages([
                "records.$index.price_list_code" => ['Urun fiyatini yazmak icin gecerli bir price list gerekli.'],
            ]);
        }

        $basePrice = BasePrice::query()
            ->where('price_list_id', $priceList->id)
            ->where('product_id', $product->id)
            ->first();

        if ($record['list_price'] === null || $record['list_price'] === '') {
            $basePrice?->delete();

            return true;
        }

        $attributes = [
            'list_price' => $record['list_price'],
            'currency' => strtoupper($this->nullableString($record['currency'] ?? null) ?? 'TRY'),
            'updated_at' => now(),
        ];

        if ($basePrice) {
            $basePrice->fill($attributes)->save();

            return true;
        }

        BasePrice::query()->create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            ...$attributes,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function buildMeta(?Product $product, array $record, ?string $externalReference): array
    {
        $meta = is_array($product?->meta) ? $product->meta : [];

        Arr::set($meta, 'integrations.logo.synced_at', now()->toIso8601String());

        if ($externalReference !== null) {
            Arr::set($meta, 'integrations.logo.external_ref', $externalReference);
        }

        if (! empty($record['meta']) && is_array($record['meta'])) {
            Arr::set($meta, 'integrations.logo.payload', $record['meta']);

            foreach (['kod1', 'kod2', 'kod3', 'specode', 'specode2', 'specode3', 'specode4', 'specode5', 'stok_turu', 'brand_code', 'category_code'] as $key) {
                $value = $this->nullableString($record['meta'][$key] ?? null);
                if ($value !== null) {
                    Arr::set($meta, $key, $value);
                }
            }
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function buildStockOnlyMeta(Product $product, array $record, ?string $externalReference): array
    {
        $meta = is_array($product->meta) ? $product->meta : [];

        Arr::set($meta, 'integrations.logo.synced_at', now()->toIso8601String());

        if ($externalReference !== null) {
            Arr::set($meta, 'integrations.logo.external_ref', $externalReference);
        }

        $logoStock = data_get($record, 'meta.logo_stock');
        if (is_array($logoStock)) {
            Arr::set($meta, 'integrations.logo.payload.logo_stock', $logoStock);
        }

        return $meta;
    }

    private function resolvePriceList(
        mixed $priceListId,
        mixed $priceListCode,
        ?PriceList $fallback = null,
    ): ?PriceList {
        if ($priceListId !== null && $priceListId !== '') {
            return PriceList::query()->find((int) $priceListId);
        }

        $normalizedCode = $this->nullableString($priceListCode);
        if ($normalizedCode !== null) {
            return PriceList::query()
                ->where('code', $normalizedCode)
                ->first();
        }

        if ($fallback) {
            return $fallback;
        }

        return PriceList::query()
            ->where('code', 'A')
            ->first()
            ?? PriceList::query()->orderBy('id')->first();
    }

    private function stableSlug(?string $code, string $name, string $prefix): string
    {
        $seed = $code ?? $name;
        $slug = Str::slug($seed);

        if ($slug === '') {
            $slug = 'default';
        }

        return "logo-{$prefix}-{$slug}";
    }

    /**
     * @param  array<int, int|string>  $productIds
     */
    private function reindexProducts(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        sort($productIds);

        foreach (array_chunk($productIds, 500) as $chunk) {
            $products = Product::query()
                ->with(['brand:id,name', 'category:id,name', 'codeAliases:id,product_id,code'])
                ->whereIn('id', $chunk)
                ->get();

            $this->productSearch->upsertProducts($products);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fillAndSave(Brand|Category $model, array $attributes): bool
    {
        $model->fill($attributes);

        if (! $model->isDirty()) {
            return false;
        }

        $model->save();

        return true;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return in_array(mb_strtoupper($normalized, 'UTF-8'), ['NULL', 'NIL', 'N/A', 'YOK', '-'], true)
            ? null
            : $normalized;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveProductName(array $record, string $sku): string
    {
        return $this->nullableString(data_get($record, 'meta.logo_name2'))
            ?? $this->nullableString(data_get($record, 'meta.name2'))
            ?? $this->nullableString(data_get($record, 'meta.description2'))
            ?? $this->nullableString(data_get($record, 'meta.raw.NAME3'))
            ?? $this->nullableString(data_get($record, 'meta.raw.NAME_3'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION3'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION3_'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION_3'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION_3_'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DESCRIPTION3'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DESCRIPTION_3'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DESC3'))
            ?? $this->nullableString(data_get($record, 'meta.raw.NAME2'))
            ?? $this->nullableString(data_get($record, 'meta.raw.NAME_2'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION2'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION2_'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION_2'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DEFINITION_2_'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DESCRIPTION2'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DESCRIPTION_2'))
            ?? $this->nullableString(data_get($record, 'meta.raw.DESC2'))
            ?? $this->nullableString($record['name'] ?? null)
            ?? $sku;
    }

    /**
     * @return array{code:string, normalized_code:string, code_type:string, brand_name:?string, source:string, meta:?array<string, mixed>}|null
     */
    private function normalizeCodeAlias(mixed $alias): ?array
    {
        if (! is_array($alias)) {
            return null;
        }

        $code = $this->nullableString($alias['code'] ?? null);
        $normalizedCode = ProductCodeNormalizer::normalize($code);

        if ($code === null || $normalizedCode === null) {
            return null;
        }

        $type = $this->nullableString($alias['type'] ?? null) ?? 'other';
        $brandName = $this->nullableString($alias['brand_name'] ?? null);
        $meta = is_array($alias['meta'] ?? null) ? $alias['meta'] : null;

        return [
            'code' => $code,
            'normalized_code' => $normalizedCode,
            'code_type' => in_array($type, ['oem', 'competitor', 'equivalent', 'other'], true) ? $type : 'other',
            'brand_name' => $brandName,
            'source' => 'logo',
            'meta' => $meta,
        ];
    }

    /**
     * @param  array{normalized_code:string, code_type:string, source:string}  $alias
     */
    private function codeAliasUniqueKey(array $alias): string
    {
        return implode('|', [
            $alias['normalized_code'],
            $alias['code_type'],
            $alias['source'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function stringField(array $record, string $key, ?string $fallback): ?string
    {
        if (! array_key_exists($key, $record)) {
            return $fallback;
        }

        return $this->nullableString($record[$key] ?? null);
    }

    private function resolveProductDescription(array $record, ?string $fallback): ?string
    {
        $description = $this->stringField($record, 'description', $fallback);
        if ($description !== null) {
            return $description;
        }

        if (! is_array($record['meta'] ?? null)) {
            return $fallback;
        }

        foreach ([
            'description',
            'description1',
            'description2',
            'description3',
            'raw.DESCRIPTION',
            'raw.DESCRIPTION1',
            'raw.DESCRIPTION_1',
            'raw.DESCRIPTION2',
            'raw.DESCRIPTION_2',
            'raw.DESCRIPTION3',
            'raw.DESCRIPTION_3',
            'raw.DEFINITION',
            'raw.DEFINITION1',
            'raw.DEFINITION_1',
            'raw.DEFINITION_2',
            'raw.DEFINITION_2_',
            'raw.DEFINITION2',
            'raw.DEFINITION_3',
            'raw.DEFINITION_3_',
            'raw.DEFINITION3',
            'raw.DESC',
            'raw.DESC1',
            'raw.DESC_1',
            'raw.DESC2',
            'raw.DESC_2',
            'raw.DESC3',
            'raw.DESC_3',
        ] as $path) {
            $value = $this->nullableString(data_get($record['meta'], $path));
            if ($value !== null) {
                return $value;
            }
        }

        return $fallback;
    }
}
