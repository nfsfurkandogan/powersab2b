<?php

namespace Tests\Feature;

use App\Models\Cashbox;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\PosExpense;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosPrintApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_print_endpoints_are_registered(): void
    {
        $this->assertTrue($this->hasRoute('GET', 'api/pos/sales/{posSale}/print'));
        $this->assertTrue($this->hasRoute('GET', 'api/pos/sales/{posSale}/print/receipt'));
        $this->assertTrue($this->hasRoute('GET', 'api/pos/reports/day-end/print'));
    }

    public function test_sale_print_returns_html(): void
    {
        [$user, $sale] = $this->createPosSaleContext();

        $this->actingAs($user);

        $this->get('/api/pos/sales/'.$sale->id.'/print')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Satış Fişi')
            ->assertSee($sale->receipt_no);
    }

    public function test_receipt_print_legacy_alias_still_returns_html(): void
    {
        [$user, $sale] = $this->createPosSaleContext();

        $this->actingAs($user);

        $this->get('/api/pos/sales/'.$sale->id.'/print/receipt')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Satış Fişi')
            ->assertSee($sale->receipt_no);
    }

    public function test_delivery_print_returns_delivery_html(): void
    {
        [$user, $sale] = $this->createPosSaleContext(documentType: 'delivery');

        $this->actingAs($user);

        $this->get('/api/pos/sales/'.$sale->id.'/print')
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Sevk İrsaliyesi')
            ->assertSee($sale->receipt_no);
    }

    public function test_day_end_print_returns_html(): void
    {
        [$user, $sale] = $this->createPosSaleContext();
        $user->forceFill([
            'menu_permissions' => ['pos', 'pos-day-end', 'delivery-notes'],
        ])->save();

        $this->actingAs($user);

        $this->get('/api/pos/reports/day-end/print?pos_session_id='.$sale->pos_session_id)
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('POS Gün Sonu Raporu')
            ->assertSee('Toplam Satış')
            ->assertSee('Masraf Toplamı');
    }

    /**
     * @return array{0: User, 1: PosSale}
     */
    private function createPosSaleContext(string $documentType = 'invoice'): array
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POS-'.Str::upper(Str::random(4)),
            'name' => 'POS Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('cashier', $dealer);

        $cashbox = Cashbox::query()->create([
            'code' => 'CB-'.Str::upper(Str::random(3)),
            'name' => 'Merkez Kasa',
            'is_active' => true,
        ]);

        $session = PosSession::query()->create([
            'cashbox_id' => $cashbox->id,
            'opened_by' => $user->id,
            'opened_at' => Carbon::now()->subHour(),
            'opening_cash' => 5000,
            'status' => 'open',
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'CR-'.Str::upper(Str::random(6)),
            'name' => 'POS Test Müşteri',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function (): Product {
            return Product::query()->create([
                'sku' => 'SKU-'.Str::upper(Str::random(6)),
                'oem_code' => 'OEM-'.Str::upper(Str::random(6)),
                'name' => 'POS Test Ürünü',
                'vat_rate' => 20,
                'is_active' => true,
            ]);
        });

        $sale = PosSale::query()->create([
            'pos_session_id' => $session->id,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'document_type' => $documentType,
            'receipt_no' => 'PS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'subtotal' => 1000,
            'discount_total' => 100,
            'vat_total' => 180,
            'grand_total' => 1080,
            'status' => 'paid',
            'created_by' => $user->id,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        PosSaleItem::query()->create([
            'pos_sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'unit_price' => 500,
            'vat_rate' => 20,
            'line_total' => 1000,
        ]);

        PosPayment::query()->create([
            'pos_sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 1080,
        ]);

        PosExpense::query()->create([
            'pos_session_id' => $session->id,
            'dealer_id' => $dealer->id,
            'expense_date' => now()->toDateString(),
            'category' => 'Kargo',
            'amount' => 120,
            'currency' => 'TRY',
            'note' => 'Kargo çıkışı',
            'created_by_user_id' => $user->id,
        ]);

        return [$user, $sale];
    }

    private function createUserWithRole(string $roleSlug, ?Dealer $dealer = null): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline(str_replace('_', ' ', $roleSlug))]
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer?->id,
            'is_active' => true,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function hasRoute(string $method, string $uri): bool
    {
        $routes = app('router')->getRoutes()->getRoutesByMethod()[$method] ?? [];

        foreach ($routes as $route) {
            if ($route->uri() === $uri) {
                return true;
            }
        }

        return false;
    }
}
