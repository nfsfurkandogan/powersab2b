<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_endpoints_are_registered(): void
    {
        $this->assertTrue($this->hasRoute('POST', 'api/auth/login'));
        $this->assertTrue($this->hasRoute('POST', 'api/auth/logout'));
        $this->assertTrue($this->hasRoute('GET', 'api/auth/me'));

        $this->assertTrue($this->hasRoute('POST', 'api/login'));
        $this->assertTrue($this->hasRoute('POST', 'api/logout'));
        $this->assertTrue($this->hasRoute('GET', 'api/me'));
    }

    public function test_login_me_logout_flow_works(): void
    {
        $dealer = $this->createDealer('DLR-AUTH-001');

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'username' => 'auth.flow',
            'email' => 'auth-flow@powersa.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->withHeaders($this->spaHeaders())->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
            'remember' => true,
        ])->assertOk()
            ->assertJsonPath('user.username', $user->username)
            ->assertJsonPath('user.email', $user->email);

        $this->withHeaders($this->spaHeaders())->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->withHeaders($this->spaHeaders())->postJson('/api/auth/logout')
            ->assertNoContent();
    }

    public function test_me_requires_authentication(): void
    {
        $this->withHeaders($this->spaHeaders())->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_requires_authentication_for_plain_api_requests_without_json_accept_header(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'username' => 'wrong.cred',
            'email' => 'wrong-cred@powersa.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->withHeaders($this->spaHeaders())->postJson('/api/auth/login', [
            'username' => 'wrong.cred',
            'password' => 'bad-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Kullanıcı adı veya şifre hatalı.');
    }

    public function test_login_rejects_inactive_user(): void
    {
        $user = User::factory()->create([
            'username' => 'inactive.user',
            'email' => 'inactive@powersa.test',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->withHeaders($this->spaHeaders())->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Kullanıcı hesabı pasif.');
    }

    public function test_salesperson_login_clears_previous_selected_customer(): void
    {
        $dealer = $this->createDealer('DLR-AUTH-SALES');
        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'CUST-AUTH-SALES',
            'name' => 'Auth Sales Customer',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Plasiyer',
            'slug' => 'salesperson',
        ]);
        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'selected_customer_id' => $customer->id,
            'username' => 'sales.auth',
            'email' => 'sales-auth@powersa.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->roles()->sync([$role->id]);

        $this->withHeaders($this->spaHeaders())->postJson('/api/auth/login', [
            'username' => $user->username,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.username', $user->username)
            ->assertJsonPath('user.selected_customer_id', null);

        $this->assertNull($user->fresh()->selected_customer_id);
    }

    public function test_legacy_login_route_alias_still_works(): void
    {
        $dealer = $this->createDealer('DLR-AUTH-LEGACY');

        $user = User::factory()->create([
            'dealer_id' => $dealer->id,
            'username' => 'legacy.auth.flow',
            'email' => 'legacy-auth-flow@powersa.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->withHeaders($this->spaHeaders())->postJson('/api/login', [
            'username' => $user->username,
            'password' => 'password',
            'remember' => true,
        ])->assertOk()
            ->assertJsonPath('user.username', $user->username)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_sanctum_csrf_cookie_endpoint_is_available_for_spa(): void
    {
        $response = $this->withHeaders($this->spaHeaders())->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $response->assertHeader('set-cookie');
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

    private function createDealer(string $code): Dealer
    {
        return Dealer::query()->create([
            'code' => $code,
            'name' => 'Dealer '.$code,
            'is_active' => true,
        ]);
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
