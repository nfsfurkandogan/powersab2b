<?php

namespace Tests\Feature;

use App\Models\Cashbox;
use App\Models\Customer;
use App\Models\CustomerCardRequest;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_dashboard_overview_with_live_counts_and_queues(): void
    {
        $dealer = $this->createDealer('DLR-ADM-OVR', true);
        $inactiveDealer = $this->createDealer('DLR-ADM-PAS', false);

        $customer = $this->createCustomer($dealer, 'ADM-001', 'Admin Cari', true);
        $this->createCustomer($dealer, 'ADM-002', 'Pasif Cari', false);

        $admin = $this->createUserWithRole('admin');
        $salesperson = $this->createUserWithRole('salesperson', $dealer);
        $point = $this->createUserWithRole('point', $inactiveDealer);
        $this->createUserWithRole('warehouse', $dealer, false);

        $cashbox = Cashbox::query()->create([
            'code' => 'CSH-ADM-001',
            'name' => 'Admin Test Kasa',
            'is_active' => true,
        ]);

        PosSession::query()->create([
            'cashbox_id' => $cashbox->id,
            'opened_by' => $point->id,
            'opened_at' => now()->subHour(),
            'opening_cash' => 500,
            'status' => 'open',
        ]);

        CustomerCardRequest::query()->create([
            'dealer_id' => $dealer->id,
            'requested_by_user_id' => $salesperson->id,
            'request_no' => 'YCK-ADM-0001',
            'company_name' => 'Yeni Cari Test',
            'contact_name' => 'Onur Kaya',
            'phone' => '05320001122',
            'city' => 'Istanbul',
            'status' => CustomerCardRequest::STATUS_SUBMITTED,
        ]);

        $product = Product::withoutEvents(function (): Product {
            return Product::query()->create([
                'sku' => 'SKU-ADM-0001',
                'oem_code' => 'OEM-ADM-0001',
                'name' => 'Admin Dashboard Product',
                'vat_rate' => 20,
                'is_active' => true,
            ]);
        });

        $approvedOrder = Order::query()->create([
            'order_no' => 'ORD-ADM-0001',
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'user_id' => $salesperson->id,
            'status' => 'approved',
            'currency' => 'TRY',
            'subtotal' => 1200,
            'discount_total' => 0,
            'tax_total' => 240,
            'grand_total' => 1440,
            'ordered_at' => now()->subDay(),
            'approved_at' => now()->subHours(3),
        ]);

        $orderItem = OrderItem::query()->create([
            'order_id' => $approvedOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'shipped_qty' => 0,
            'unit_net_price' => 600,
            'discount_rate' => 0,
            'tax_rate' => 20,
            'line_total' => 1200,
            'currency' => 'TRY',
        ]);

        ReturnRequest::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'order_id' => $approvedOrder->id,
            'order_item_id' => $orderItem->id,
            'requested_by_user_id' => $salesperson->id,
            'request_no' => 'RET-ADM-0001',
            'request_type' => ReturnRequest::TYPE_FAULTY,
            'status' => ReturnRequest::STATUS_REVIEWING,
            'reason_code' => 'defect',
            'quantity' => 1,
            'unit_price' => 600,
            'currency' => 'TRY',
        ]);

        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard/overview');

        $response
            ->assertOk()
            ->assertJsonPath('summary.users_total', 4)
            ->assertJsonPath('summary.active_users_total', 3)
            ->assertJsonPath('summary.dealers_total', 2)
            ->assertJsonPath('summary.active_dealers_total', 1)
            ->assertJsonPath('summary.customers_total', 2)
            ->assertJsonPath('summary.active_customers_total', 1)
            ->assertJsonPath('summary.open_pos_sessions_total', 1)
            ->assertJsonPath('summary.warehouse_ready_orders_total', 1)
            ->assertJsonPath('summary.pending_customer_card_requests_total', 1)
            ->assertJsonPath('summary.pending_return_requests_total', 1)
            ->assertJsonCount(4, 'recent_users')
            ->assertJsonCount(2, 'recent_dealers')
            ->assertJsonCount(1, 'open_pos_sessions')
            ->assertJsonFragment([
                'slug' => 'admin',
                'count' => 1,
            ])
            ->assertJsonFragment([
                'slug' => 'salesperson',
                'count' => 1,
            ])
            ->assertJsonFragment([
                'slug' => 'point',
                'count' => 1,
            ])
            ->assertJsonFragment([
                'slug' => 'warehouse',
                'count' => 1,
            ])
            ->assertJsonFragment([
                'company_name' => 'Yeni Cari Test',
                'status' => CustomerCardRequest::STATUS_SUBMITTED,
            ])
            ->assertJsonFragment([
                'request_no' => 'RET-ADM-0001',
                'request_type' => ReturnRequest::TYPE_FAULTY,
            ])
            ->assertJsonFragment([
                'order_no' => 'ORD-ADM-0001',
                'status' => 'approved',
            ])
            ->assertJsonFragment([
                'name' => $admin->name,
                'email' => $admin->email,
            ])
            ->assertJsonFragment([
                'name' => $dealer->name,
                'code' => $dealer->code,
            ])
            ->assertJsonFragment([
                'code' => $cashbox->code,
                'name' => $point->name,
            ]);
    }

    public function test_non_admin_cannot_read_dashboard_overview(): void
    {
        $dealer = $this->createDealer('DLR-ADM-DENY', true);
        $salesperson = $this->createUserWithRole('salesperson', $dealer);

        $this->actingAs($salesperson);

        $this->getJson('/api/admin/dashboard/overview')
            ->assertForbidden();
    }

    private function createDealer(string $code, bool $isActive): Dealer
    {
        return Dealer::query()->create([
            'code' => $code,
            'name' => 'Dealer '.$code,
            'is_active' => $isActive,
        ]);
    }

    private function createCustomer(Dealer $dealer, string $code, string $name, bool $isActive): Customer
    {
        return Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => $code,
            'name' => $name,
            'is_active' => $isActive,
        ]);
    }

    private function createUserWithRole(string $roleSlug, ?Dealer $dealer = null, bool $isActive = true): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline(str_replace('_', ' ', $roleSlug))]
        );

        $user = User::factory()->create([
            'dealer_id' => $dealer?->id,
            'is_active' => $isActive,
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }
}
