<?php

namespace Tests\Feature;

use App\Models\BasePrice;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\HotProduct;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_catalogs_from_selected_customer_dealer_context(): void
    {
        [$admin, $product] = $this->createCatalogContext();

        $this->actingAs($admin);

        $newProductsResponse = $this->getJson('/api/catalog/new-products?limit=5');
        $newProductsResponse
            ->assertOk()
            ->assertJsonPath('catalog', 'new-products')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.brand.name', 'Catalog Brand');

        $hotProductsResponse = $this->getJson('/api/catalog/hot-products?limit=5');
        $hotProductsResponse
            ->assertOk()
            ->assertJsonPath('catalog', 'hot-products')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.rank', 1);
    }

    public function test_new_products_can_be_limited_to_recent_days(): void
    {
        [$admin, $product] = $this->createCatalogContext();

        $oldProduct = Product::withoutEvents(function () use ($product): Product {
            $created = Product::query()->create([
                'sku' => 'CAT-OLD-'.Str::upper(Str::random(4)),
                'oem_code' => 'CAT-OLD-OEM-'.Str::upper(Str::random(4)),
                'name' => 'Catalog Old Product',
                'brand_id' => $product->brand_id,
                'vat_rate' => 20,
                'is_active' => true,
                'meta' => $this->logoProductMeta(),
            ]);

            $created->forceFill([
                'created_at' => now()->subDays(45),
                'updated_at' => now()->subDays(45),
            ])->save();

            return $created;
        });

        BasePrice::query()->create([
            'price_list_id' => PriceList::query()->where('code', 'A')->value('id'),
            'product_id' => $oldProduct->id,
            'list_price' => 99.90,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        StockSummary::query()->create([
            'product_id' => $oldProduct->id,
            'available_total' => 7,
            'reserved_total' => 0,
            'updated_at' => now(),
        ]);

        $this->actingAs($admin);

        $response = $this->getJson('/api/catalog/new-products?limit=10&days=30')
            ->assertOk()
            ->assertJsonPath('catalog', 'new-products');

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($product->id, $ids);
        $this->assertNotContains($oldProduct->id, $ids);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function createCatalogContext(): array
    {
        $priceList = PriceList::query()->where('code', 'A')->firstOrFail();

        $dealer = Dealer::query()->create([
            'code' => 'DLR-CAT-'.Str::upper(Str::random(4)),
            'name' => 'Catalog Dealer',
            'is_active' => true,
            'price_list_id' => $priceList->id,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'CAT-001',
            'name' => 'Catalog Customer',
            'is_active' => true,
        ]);

        $adminRole = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );

        $admin = User::factory()->create([
            'dealer_id' => null,
            'selected_customer_id' => $customer->id,
            'is_active' => true,
        ]);
        $admin->roles()->sync([$adminRole->id]);

        $brand = Brand::query()->create([
            'name' => 'Catalog Brand',
            'slug' => 'catalog-brand-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function () use ($brand): Product {
            $created = Product::query()->create([
                'sku' => 'CAT-SKU-'.Str::upper(Str::random(4)),
                'oem_code' => 'CAT-OEM-'.Str::upper(Str::random(4)),
                'name' => 'Catalog Test Product',
                'brand_id' => $brand->id,
                'vat_rate' => 20,
                'is_active' => true,
                'meta' => $this->logoProductMeta(),
            ]);

            $created->forceFill([
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ])->save();

            return $created;
        });

        BasePrice::query()->create([
            'price_list_id' => $priceList->id,
            'product_id' => $product->id,
            'list_price' => 125.50,
            'currency' => 'TRY',
            'updated_at' => now(),
        ]);

        StockSummary::query()->create([
            'product_id' => $product->id,
            'available_total' => 18,
            'reserved_total' => 0,
            'updated_at' => now(),
        ]);

        HotProduct::query()->create([
            'product_id' => $product->id,
            'rank' => 1,
        ]);

        return [$admin, $product];
    }

    /**
     * @return array<string, mixed>
     */
    private function logoProductMeta(): array
    {
        return [
            'integrations' => [
                'logo' => [
                    'synced_at' => now()->toJSON(),
                    'external_ref' => 'LOGO-'.Str::upper(Str::random(6)),
                ],
            ],
        ];
    }
}
