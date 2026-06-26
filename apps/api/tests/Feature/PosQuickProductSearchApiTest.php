<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\ProductCodeAlias;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosQuickProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_quick_search_returns_product_by_competitor_code_alias(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-'.Str::upper(Str::random(4)),
            'name' => 'POS Test Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'cashier'],
            ['name' => 'Cashier']
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'CR-'.Str::upper(Str::random(6)),
            'name' => 'POS Search Customer',
            'is_active' => true,
        ]);

        $brand = Brand::query()->create([
            'name' => 'POS Search Brand',
            'slug' => 'pos-search-brand',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function () use ($brand): Product {
            return Product::query()->create([
                'brand_id' => $brand->id,
                'sku' => 'PWS-3A760',
                'oem_code' => '3A760',
                'name' => 'POS Quick Search Test Product',
                'unit' => 'adet',
                'vat_rate' => 20.00,
                'is_active' => true,
                'meta' => [
                    'integrations' => [
                        'logo' => [
                            'synced_at' => now()->toIso8601String(),
                            'external_ref' => '1001',
                        ],
                    ],
                ],
            ]);
        });

        ProductCodeAlias::query()->create([
            'product_id' => $product->id,
            'code' => 'WH760',
            'normalized_code' => 'WH760',
            'code_type' => 'competitor',
            'brand_name' => 'WUNDER',
            'source' => 'logo',
        ]);

        DB::table('stock_summary')->insert([
            'product_id' => $product->id,
            'available_total' => 9,
            'reserved_total' => 0,
            'updated_at' => now(),
        ]);

        DB::table('base_prices')->insert([
            'price_list_id' => $priceListId,
            'product_id' => $product->id,
            'list_price' => 155.00,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/products/quick-search?q=WH760&limit=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $product->id);
        $response->assertJsonPath('data.0.sku', 'PWS-3A760');
    }

    public function test_pos_quick_search_returns_logo_product_without_stock_by_default(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-'.Str::upper(Str::random(4)),
            'name' => 'POS Test Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'cashier'],
            ['name' => 'Cashier']
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $brand = Brand::query()->create([
            'name' => 'POS Logo Brand',
            'slug' => 'pos-logo-brand',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function () use ($brand): Product {
            return Product::query()->create([
                'brand_id' => $brand->id,
                'sku' => 'CS0040',
                'oem_code' => null,
                'name' => 'Logo Synced Product Without Stock',
                'unit' => 'adet',
                'vat_rate' => 20.00,
                'is_active' => true,
                'meta' => [
                    'integrations' => [
                        'logo' => [
                            'synced_at' => now()->toIso8601String(),
                            'external_ref' => '1002',
                        ],
                    ],
                ],
            ]);
        });

        DB::table('base_prices')->insert([
            'price_list_id' => $priceListId,
            'product_id' => $product->id,
            'list_price' => 420.00,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/products/quick-search?q=CS%200040&limit=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $product->id);
        $response->assertJsonPath('data.0.sku', 'CS0040');
        $response->assertJsonPath('data.0.available_total', 0);

        $legacyStockParamResponse = $this->getJson('/api/pos/products/quick-search?q=CS%200040&limit=5&in_stock=1');

        $legacyStockParamResponse->assertOk();
        $legacyStockParamResponse->assertJsonCount(1, 'data');
        $legacyStockParamResponse->assertJsonPath('data.0.id', $product->id);

        $partialCodeResponse = $this->getJson('/api/pos/products/quick-search?q=0040&limit=5');

        $partialCodeResponse->assertOk();
        $partialCodeResponse->assertJsonCount(1, 'data');
        $partialCodeResponse->assertJsonPath('data.0.id', $product->id);
    }

    public function test_pos_quick_search_matches_compact_query_against_separated_product_name(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-OIL-'.Str::upper(Str::random(4)),
            'name' => 'POS Oil Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'point'],
            ['name' => 'Point']
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $brand = Brand::query()->create([
            'name' => 'POS Oil Brand',
            'slug' => 'pos-oil-brand',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function () use ($brand): Product {
            return Product::query()->create([
                'brand_id' => $brand->id,
                'sku' => 'PWS-OIL-010',
                'oem_code' => null,
                'name' => '10W-40 EXTRA SL/CF SEMI SYNTHETIC',
                'unit' => 'adet',
                'vat_rate' => 20.00,
                'is_active' => true,
                'meta' => [
                    'integrations' => [
                        'logo' => [
                            'synced_at' => now()->toIso8601String(),
                            'external_ref' => 'OIL-010',
                        ],
                    ],
                ],
            ]);
        });

        DB::table('base_prices')->insert([
            'price_list_id' => $priceListId,
            'product_id' => $product->id,
            'list_price' => 170.00,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/products/quick-search?q=10w40&limit=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $product->id);
        $response->assertJsonPath('data.0.sku', 'PWS-OIL-010');
    }

    public function test_pos_quick_search_exact_code_uses_product_search_group_scope(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-'.Str::upper(Str::random(4)),
            'name' => 'POS Test Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'cashier'],
            ['name' => 'Cashier']
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $brand = Brand::query()->create([
            'name' => 'POS Group Brand',
            'slug' => 'pos-group-brand',
            'is_active' => true,
        ]);

        $mainProduct = $this->createLogoProduct($brand->id, 'CS 0040', 'CLIO 1.4', 'CLIO-GROUP', 'E');
        $sameGroupProduct = $this->createLogoProduct($brand->id, '7O 800', 'CLIO 1.4', 'CLIO-GROUP', 'E');
        $sameGroupSecondProduct = $this->createLogoProduct($brand->id, '0451103336', 'CLIO 1.4', 'CLIO-GROUP', 'E');
        $equivalentProduct = $this->createLogoProduct($brand->id, 'W 75/3', 'CLIO 1.4', 'CLIO-GROUP', 'H');

        foreach ([$mainProduct, $sameGroupProduct, $sameGroupSecondProduct, $equivalentProduct] as $product) {
            DB::table('base_prices')->insert([
                'price_list_id' => $priceListId,
                'product_id' => $product->id,
                'list_price' => 100.00,
                'currency' => 'TRY',
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/products/quick-search?q=cs0040&limit=10');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonMissing(['id' => $equivalentProduct->id]);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame($mainProduct->id, $ids[0]);
        $this->assertContains($mainProduct->id, $ids);
        $this->assertContains($sameGroupProduct->id, $ids);
        $this->assertContains($sameGroupSecondProduct->id, $ids);
    }

    public function test_pos_quick_search_finds_logo_product_by_raw_logo_stock_code(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-'.Str::upper(Str::random(4)),
            'name' => 'POS Test Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'cashier'],
            ['name' => 'Cashier']
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $brand = Brand::query()->create([
            'name' => 'POS Raw Logo Brand',
            'slug' => 'pos-raw-logo-brand',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function () use ($brand): Product {
            return Product::query()->create([
                'brand_id' => $brand->id,
                'sku' => 'PWS-PRIMARY-001',
                'oem_code' => null,
                'name' => 'Raw Logo Stock Code Product',
                'unit' => 'adet',
                'vat_rate' => 20.00,
                'is_active' => true,
                'meta' => [
                    'integrations' => [
                        'logo' => [
                            'synced_at' => now()->toIso8601String(),
                            'external_ref' => '1003',
                            'payload' => [
                                'raw' => [
                                    'STOKKODU' => 'LG-STK-7788',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        });

        DB::table('base_prices')->insert([
            'price_list_id' => $priceListId,
            'product_id' => $product->id,
            'list_price' => 510.00,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/products/quick-search?q=LG-STK-7788&limit=5');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $product->id);
        $response->assertJsonPath('data.0.sku', 'PWS-PRIMARY-001');
    }

    public function test_pos_quick_search_returns_logo_warehouse_stock_locations(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-'.Str::upper(Str::random(4)),
            'name' => 'POS Test Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'point'],
            ['name' => 'Point']
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'is_active' => true,
            'feature_permissions' => [
                'search.stock',
                'search.stock.warehouse.erzurum_depo',
                'search.stock.warehouse.batum',
            ],
        ]);
        $user->roles()->sync([$role->id]);

        $brand = Brand::query()->create([
            'name' => 'POS Logo Stock Brand',
            'slug' => 'pos-logo-stock-brand',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function () use ($brand): Product {
            return Product::query()->create([
                'brand_id' => $brand->id,
                'sku' => 'CS0040',
                'oem_code' => null,
                'name' => 'Logo Warehouse Stock Product',
                'unit' => 'adet',
                'vat_rate' => 20.00,
                'is_active' => true,
                'meta' => [
                    'integrations' => [
                        'logo' => [
                            'synced_at' => now()->toIso8601String(),
                            'external_ref' => '1004',
                            'payload' => [
                                'logo_stock' => [
                                    'warehouses' => [
                                        [
                                            'warehouse_code' => '1',
                                            'warehouse_name' => 'ERZURUM DEPO',
                                            'available_total' => 7,
                                            'shelf_address' => 'D.12',
                                        ],
                                        [
                                            'warehouse_code' => '4',
                                            'warehouse_name' => 'BATUM DEPO',
                                            'available_total' => 3,
                                            'shelf_address' => 'B.4',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        });

        DB::table('stock_summary')->insert([
            'product_id' => $product->id,
            'available_total' => 10,
            'reserved_total' => 0,
            'updated_at' => now(),
        ]);

        DB::table('base_prices')->insert([
            'price_list_id' => $priceListId,
            'product_id' => $product->id,
            'list_price' => 170.00,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/products/quick-search?q=CS0040&limit=5');

        $response->assertOk();
        $response->assertJsonPath('data.0.available_total', 10);
        $response->assertJsonPath('data.0.stock_locations.0.branch', 'ERZURUM DEPO');
        $response->assertJsonPath('data.0.stock_locations.0.stock', 7);
        $response->assertJsonPath('data.0.stock_locations.0.shelf_address', 'D.12');
        $response->assertJsonPath('data.0.stock_locations.1.branch', 'BATUM DEPO');
        $response->assertJsonPath('data.0.stock_locations.1.stock', 3);
        $response->assertJsonPath('data.0.stock_locations.1.shelf_address', 'B.4');
    }

    public function test_pos_customer_selection_payload_includes_dealer_id_for_admin_context(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-CUSTOMER-'.Str::upper(Str::random(4)),
            'name' => 'POS Customer Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );

        $user = User::factory()->create([
            'dealer_id' => null,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'POS-DEALER-CTX',
            'name' => 'POS Dealer Context Customer',
            'source_system' => 'logo',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/customers?q=POS-DEALER-CTX&source_system=logo&limit=5');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $customer->id);
        $response->assertJsonPath('data.0.dealer_id', $dealer->id);
    }

    public function test_admin_pos_quick_search_uses_selected_customer_dealer_context(): void
    {
        $priceListId = (int) DB::table('price_lists')->where('code', 'A')->value('id');

        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-ADMIN-'.Str::upper(Str::random(4)),
            'name' => 'POS Admin Dealer',
            'price_list_id' => $priceListId > 0 ? $priceListId : null,
            'is_active' => true,
        ]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'POS-ADMIN-CUSTOMER',
            'name' => 'POS Admin Context Customer',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'dealer_id' => null,
            'selected_customer_id' => $customer->id,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        $brand = Brand::query()->create([
            'name' => 'POS Admin Brand',
            'slug' => 'pos-admin-brand',
            'is_active' => true,
        ]);

        $product = $this->createLogoProduct($brand->id, 'ADM-1401', 'Admin Context Product', 'ADMIN-GROUP', 'D');

        DB::table('base_prices')->insert([
            'price_list_id' => $priceListId,
            'product_id' => $product->id,
            'list_price' => 210.00,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/pos/products/quick-search?q=ADM-1401&limit=5&code_only=1');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $product->id);
        $response->assertJsonPath('data.0.sku', 'ADM-1401');
    }

    private function createLogoProduct(int $brandId, string $sku, string $name, string $groupCode, string $specode4): Product
    {
        return Product::withoutEvents(function () use ($brandId, $sku, $name, $groupCode, $specode4): Product {
            return Product::query()->create([
                'brand_id' => $brandId,
                'sku' => $sku,
                'oem_code' => null,
                'name' => $name,
                'unit' => 'adet',
                'vat_rate' => 20.00,
                'is_active' => true,
                'meta' => [
                    'specode4' => $specode4,
                    'category_code' => $groupCode,
                    'integrations' => [
                        'logo' => [
                            'synced_at' => now()->toIso8601String(),
                            'external_ref' => 'LOGO-'.$sku,
                            'payload' => [
                                'category_code' => $groupCode,
                                'raw' => [
                                    'SPECODE4' => $specode4,
                                    'STGRPCODE' => $groupCode,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        });
    }
}
