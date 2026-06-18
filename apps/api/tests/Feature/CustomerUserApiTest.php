<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_customers_and_create_customer_login(): void
    {
        $this->seed(RoleSeeder::class);

        $dealer = $this->createDealer('DLR-CUSR-001');
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createCustomer($dealer, '120-25-999', 'Erzurum Test Cari');

        $this->actingAs($admin);

        $this->getJson('/api/customer-users?q=120-25&limit=10')
            ->assertOk()
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('data.0.code', '120-25-999')
            ->assertJsonPath('data.0.name', 'Erzurum Test Cari')
            ->assertJsonPath('data.0.username', '120-25-999')
            ->assertJsonPath('data.0.user', null);

        $this->postJson("/api/customer-users/{$customer->id}", [
            'password' => '250250',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', '120-25-999')
            ->assertJsonPath('data.username', '120-25-999')
            ->assertJsonPath('data.user.username', '120-25-999')
            ->assertJsonPath('data.user.is_active', true)
            ->assertJsonPath('data.user.menu_permissions', ['dashboard', 'notes', 'search', 'catalogs', 'cart', 'orders', 'ledger'])
            ->assertJsonPath('data.user.feature_permissions.0', 'dashboard.summary');

        $createdUser = User::query()
            ->where('username', '120-25-999')
            ->firstOrFail();

        $this->assertTrue(Hash::check('250250', $createdUser->password));
        $this->assertSame($dealer->id, $createdUser->dealer_id);
        $this->assertSame($customer->id, $createdUser->selected_customer_id);
        $this->assertTrue($createdUser->roles()->where('slug', 'customer')->exists());
    }

    public function test_customer_login_preserves_own_selected_customer(): void
    {
        $this->seed(RoleSeeder::class);

        $dealer = $this->createDealer('DLR-CUSR-LOGIN');
        $customer = $this->createCustomer($dealer, '120-25-100', 'Login Cari');
        $user = $this->createUserWithRole('customer', $dealer, [
            'selected_customer_id' => $customer->id,
            'customer_scope' => 'assigned',
            'username' => '120-25-100',
            'password' => '250250',
            'menu_permissions' => ['dashboard', 'search', 'catalogs', 'cart', 'orders', 'ledger'],
            'feature_permissions' => ['search.prices', 'search.add_to_cart'],
        ]);

        $this->withHeaders($this->spaHeaders())->postJson('/api/login', [
            'username' => $user->username,
            'password' => '250250',
        ])
            ->assertOk()
            ->assertJsonPath('user.username', '120-25-100')
            ->assertJsonPath('user.selected_customer_id', $customer->id)
            ->assertJsonPath('user.selected_customer.code', $customer->code)
            ->assertJsonPath('user.menu_permissions', ['dashboard', 'search', 'catalogs', 'cart', 'orders', 'ledger'])
            ->assertJsonPath('user.feature_permissions', ['search.prices', 'search.add_to_cart']);

        $this->assertSame($customer->id, $user->fresh()->selected_customer_id);
    }

    public function test_customer_user_password_can_be_reset_for_existing_customer_user(): void
    {
        $this->seed(RoleSeeder::class);

        $dealer = $this->createDealer('DLR-CUSR-RST');
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createCustomer($dealer, '120-61-777', 'Trabzon Cari');
        $customerUser = $this->createUserWithRole('customer', $dealer, [
            'selected_customer_id' => $customer->id,
            'customer_scope' => 'assigned',
            'username' => '120-61-777',
            'password' => 'oldpass',
        ]);

        $this->actingAs($admin);

        $this->postJson("/api/customer-users/{$customer->id}", [
            'password' => 'newpass',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $customerUser->id)
            ->assertJsonPath('data.user.username', '120-61-777');

        $this->assertTrue(Hash::check('newpass', $customerUser->fresh()->password));
    }

    public function test_customer_user_permissions_and_discount_can_be_saved(): void
    {
        $this->seed(RoleSeeder::class);

        $dealer = $this->createDealer('DLR-CUSR-PERM');
        $admin = $this->createUserWithRole('admin');
        $customer = $this->createCustomer($dealer, '120-25-555', 'Yetki Cari');

        $this->actingAs($admin);

        $this->postJson("/api/customer-users/{$customer->id}", [
            'password' => '250250',
            'menu_permissions' => ['dashboard', 'search', 'cart'],
            'feature_permissions' => ['dashboard.summary', 'search.prices', 'cart.checkout'],
            'special_discount_rate' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('data.special_discount_rate', '10.00')
            ->assertJsonPath('data.user.menu_permissions', ['dashboard', 'search', 'cart'])
            ->assertJsonPath('data.user.feature_permissions', ['dashboard.summary', 'search.prices', 'cart.checkout']);

        $createdUser = User::query()
            ->where('username', '120-25-555')
            ->firstOrFail();

        $this->assertSame(['dashboard', 'search', 'cart'], $createdUser->menu_permissions);
        $this->assertSame(['dashboard.summary', 'search.prices', 'cart.checkout'], $createdUser->feature_permissions);
        $this->assertSame('10.00', $customer->fresh()->meta['special_discount_rate']);
    }

    private function createDealer(string $code): Dealer
    {
        return Dealer::query()->create([
            'code' => $code,
            'name' => 'Dealer '.$code,
            'is_active' => true,
        ]);
    }

    private function createCustomer(Dealer $dealer, string $code, string $name): Customer
    {
        return Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'logo',
            'code' => $code,
            'name' => $name,
            'phone' => '05550000000',
            'city' => 'ERZURUM',
            'district' => 'MERKEZ',
            'is_active' => true,
        ]);
    }

    private function createUserWithRole(string $roleSlug, ?Dealer $dealer = null, array $overrides = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline(str_replace('_', ' ', $roleSlug))]
        );

        $user = User::factory()->create(array_merge([
            'dealer_id' => $dealer?->id,
            'selected_customer_id' => null,
            'customer_scope' => $roleSlug === 'customer' ? 'assigned' : 'dealer',
            'is_active' => true,
        ], $overrides));

        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/login',
            'Accept' => 'application/json',
        ];
    }
}
