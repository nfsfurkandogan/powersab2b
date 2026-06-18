<?php

namespace Tests\Feature;

use App\Models\Cashbox;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\LedgerEntry;
use App\Models\PosExpense;
use App\Models\PosSale;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PosPointCustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PointPosAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_point_user_can_list_pos_customers_in_own_dealer_scope(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $otherDealer = Dealer::query()->create([
            'code' => 'DLR-OTHER-'.Str::upper(Str::random(4)),
            'name' => 'Other Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $visibleCustomer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'POINT-NAKIT',
            'name' => 'Point Nakit Cari',
            'is_active' => true,
        ]);

        Customer::query()->create([
            'dealer_id' => $otherDealer->id,
            'code' => 'POINT-KART',
            'name' => 'Other Dealer Customer',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->getJson('/api/pos/customers?q=POINT&limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $visibleCustomer->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_warehouse_user_with_pos_menu_can_list_pos_customers(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-WH-POS-'.Str::upper(Str::random(4)),
            'name' => 'Warehouse Pos Dealer',
            'is_active' => true,
        ]);

        $otherDealer = Dealer::query()->create([
            'code' => 'DLR-OTHER-'.Str::upper(Str::random(4)),
            'name' => 'Other Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('warehouse', $dealer);
        $user->forceFill([
            'menu_permissions' => ['warehouse', 'pos'],
        ])->save();

        $visibleCustomer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'WH-POS-NAKIT',
            'name' => 'Depo Hızlı Satış Cari',
            'is_active' => true,
        ]);

        Customer::query()->create([
            'dealer_id' => $otherDealer->id,
            'code' => 'WH-POS-DIS',
            'name' => 'Diğer Depo Cari',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->getJson('/api/pos/customers?q=WH-POS&limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $visibleCustomer->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_point_branch_user_can_list_unassigned_logo_customers_for_pos(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $otherDealer = Dealer::query()->create([
            'code' => 'DLR-OTHER-'.Str::upper(Str::random(4)),
            'name' => 'Other Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer, [
            'customer_scope' => 'branch',
            'branch_code' => 'ERZURUM',
        ]);

        $visibleLogoCustomer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'logo',
            'code' => '120-25-020',
            'name' => 'POLAT KARDEŞLER YAVUZ POLAT',
            'branch_code' => null,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'b2b',
            'code' => 'B2B-POLAT',
            'name' => 'B2B POLAT',
            'branch_code' => null,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'dealer_id' => $otherDealer->id,
            'source_system' => 'logo',
            'code' => '120-25-021',
            'name' => 'OTHER POLAT',
            'branch_code' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->getJson('/api/pos/customers?source_system=logo&q=polat&limit=10')
            ->assertOk()
            ->assertJsonPath('total_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleLogoCustomer->id);
    }

    public function test_point_user_can_open_read_and_close_pos_session(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'menu_permissions' => ['pos', 'pos-expenses', 'pos-day-end', 'delivery-notes'],
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => 'CB-'.Str::upper(Str::random(3)),
            'name' => 'Point Kasa',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 250,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->getJson('/api/pos/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.cashbox.id', $cashbox->id)
            ->assertJsonPath('data.status', 'open');

        $this->postJson('/api/pos/sessions/close', [
            'cashbox_id' => $cashbox->id,
            'closing_cash_counted' => 250,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_point_user_can_open_pos_session_without_sending_cashbox_id(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $this->actingAs($user);

        $this->postJson('/api/pos/sessions/open', [
            'opening_cash' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.cashbox.code', 'POINT-'.$user->id);
    }

    public function test_point_user_can_open_pos_session_with_user_logo_cashbox(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'logo_cashbox_code' => '100.01.002',
            'logo_cashbox_name' => 'Ahmet Arac Kasasi',
        ])->save();

        $this->actingAs($user);

        $this->postJson('/api/pos/sessions/open', [
            'opening_cash' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.cashbox.code', '100.01.002')
            ->assertJsonPath('data.cashbox.name', 'Ahmet Arac Kasasi');
    }

    public function test_pos_point_customer_seeder_creates_anonymous_customer_for_point_dealer(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $this->createUserWithRole('point', $dealer);

        $this->seed(PosPointCustomerSeeder::class);

        $customer = Customer::query()
            ->where('dealer_id', $dealer->id)
            ->where('code', 'POINT-CARISI-OLMAYAN')
            ->first();

        $this->assertNotNull($customer);
        $this->assertSame('CARİSİ OLMAYAN HIZLI SATIŞ', $customer->name);
        $this->assertTrue((bool) data_get($customer->meta, 'anonymous_sale'));
    }

    public function test_point_user_can_record_pos_expense_and_see_it_in_day_end_report(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'menu_permissions' => ['pos', 'pos-expenses', 'pos-day-end', 'delivery-notes'],
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => 'CB-'.Str::upper(Str::random(3)),
            'name' => 'Point Kasa',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $openResponse = $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 500,
        ])->assertCreated();

        $sessionId = (int) $openResponse->json('data.id');

        $this->postJson('/api/pos/expenses', [
            'pos_session_id' => $sessionId,
            'amount' => 125.50,
            'category' => 'Kargo',
            'note' => 'Acil sevkiyat',
        ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'Kargo')
            ->assertJsonPath('data.amount', '125.50')
            ->assertJsonPath('data.currency', 'GEL');

        $this->getJson('/api/pos/reports/day-end?pos_session_id='.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.summary.expense_count', 1)
            ->assertJsonPath('data.summary.expense_total', '125.50')
            ->assertJsonPath('data.summary.expected_cash', '374.50')
            ->assertJsonPath('data.expenses.by_category.0.category', 'Kargo');

        $expense = PosExpense::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('integration_sync_states', [
            'domain' => 'pos-expenses',
            'entity_type' => PosExpense::class,
            'entity_id' => $expense->id,
            'status' => 'queued',
        ]);

        config(['integrations.logo.pos_expense_sync_key' => 'test-pos-expense-key']);

        $this
            ->withHeader('X-Integration-Key', 'test-pos-expense-key')
            ->getJson('/api/integrations/logo/pos-expenses/pending?limit=10')
            ->assertOk()
            ->assertJsonPath('records.0.pos_expense_id', $expense->id)
            ->assertJsonPath('records.0.category', 'Kargo')
            ->assertJsonPath('records.0.amount', '125.50')
            ->assertJsonPath('records.0.currency', 'GEL')
            ->assertJsonPath('records.0.cashbox_code', $cashbox->code);
    }

    public function test_logo_pos_expense_sync_imports_batum_cashbox_expenses_into_current_session(): void
    {
        config(['integrations.logo.pos_expense_sync_key' => 'test-pos-expense-key']);

        $dealer = Dealer::query()->create([
            'code' => 'DLR-BATUM-EXP-'.Str::upper(Str::random(4)),
            'name' => 'Batum Expense Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'menu_permissions' => ['pos', 'pos-expenses', 'pos-day-end'],
            'branch_code' => 'BATUM',
            'region_code' => 'BATUM',
            'logo_cashbox_code' => '100.01.002',
            'logo_cashbox_name' => 'BATUM B2B VE HIZLI SATIS Kasasi',
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => '100.01.002',
            'name' => 'BATUM B2B VE HIZLI SATIS Kasasi',
            'is_active' => true,
        ]);

        $session = PosSession::query()->create([
            'cashbox_id' => $cashbox->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 0,
            'status' => 'open',
        ]);

        $this
            ->withHeader('X-Integration-Key', 'test-pos-expense-key')
            ->postJson('/api/integrations/logo/pos-expenses/sync', [
                'dealer_code' => $dealer->code,
                'cashbox_code' => '100.01.002',
                'records' => [
                    [
                        'external_ref' => 'KSLINES-9981',
                        'expense_date' => '2026-06-05',
                        'category' => 'Yakit',
                        'amount' => 75.25,
                        'currency' => 'GEL',
                        'note' => 'Logo yakit masrafi',
                        'reference_no' => 'M-9981',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.updated', 0);

        $expense = PosExpense::query()->firstOrFail();
        $this->assertSame($session->id, $expense->pos_session_id);
        $this->assertSame('logo', data_get($expense->meta, 'source_system'));

        $this->actingAs($user);

        $this->getJson('/api/pos/expenses?pos_session_id='.$session->id.'&cashbox_id='.$cashbox->id)
            ->assertOk()
            ->assertJsonPath('data.0.category', 'Yakit')
            ->assertJsonPath('data.0.amount', '75.25')
            ->assertJsonPath('data.0.source_system', 'logo')
            ->assertJsonPath('data.0.source_label', 'Logo kaydı')
            ->assertJsonPath('data.0.logo_sync_status', 'synced')
            ->assertJsonPath('data.0.logo_external_ref', 'KSLINES-9981');

        $this->getJson('/api/pos/reports/day-end?pos_session_id='.$session->id)
            ->assertOk()
            ->assertJsonPath('data.summary.expense_count', 1)
            ->assertJsonPath('data.summary.expense_total', '75.25');
    }

    public function test_warehouse_user_with_pos_menu_can_record_expense_and_read_day_end(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-WH-POS-'.Str::upper(Str::random(4)),
            'name' => 'Warehouse Pos Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('warehouse', $dealer);
        $user->forceFill([
            'menu_permissions' => ['warehouse', 'pos', 'pos-expenses', 'pos-day-end'],
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => 'WH-CB-'.Str::upper(Str::random(3)),
            'name' => 'Depo Kasa',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $openResponse = $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 300,
        ])->assertCreated();

        $sessionId = (int) $openResponse->json('data.id');

        $this->postJson('/api/pos/expenses', [
            'pos_session_id' => $sessionId,
            'amount' => 45,
            'category' => 'Depo',
            'note' => 'Depo masrafı',
        ])
            ->assertCreated()
            ->assertJsonPath('data.category', 'Depo')
            ->assertJsonPath('data.amount', '45.00');

        $this->getJson('/api/pos/reports/day-end?pos_session_id='.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.summary.expense_count', 1)
            ->assertJsonPath('data.summary.expense_total', '45.00');
    }

    public function test_pos_menu_user_without_cashbox_uses_own_session_scope(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-WH-POS-OWN-'.Str::upper(Str::random(4)),
            'name' => 'Warehouse Own Pos Dealer',
            'is_active' => true,
        ]);

        $otherUser = $this->createUserWithRole('point', $dealer);
        $otherCashbox = Cashbox::query()->create([
            'code' => 'POINT-'.$otherUser->id,
            'name' => 'Other Point Kasasi',
            'is_active' => true,
        ]);
        PosSession::query()->create([
            'cashbox_id' => $otherCashbox->id,
            'opened_by' => $otherUser->id,
            'opened_at' => now()->subHour(),
            'opening_cash' => 0,
            'status' => 'open',
        ]);

        $user = $this->createUserWithRole('warehouse', $dealer);
        $user->forceFill([
            'menu_permissions' => ['warehouse', 'pos', 'pos-expenses', 'pos-day-end'],
        ])->save();

        $this->actingAs($user);

        $this->getJson('/api/pos/sessions/current')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->postJson('/api/pos/sessions/open', [
            'opening_cash' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.cashbox.code', 'POINT-'.$user->id)
            ->assertJsonPath('data.opened_by.id', $user->id);
    }

    public function test_point_user_can_collect_customer_payment_and_it_reflects_in_day_end(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'menu_permissions' => ['pos', 'pos-day-end', 'delivery-notes'],
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => 'CB-'.Str::upper(Str::random(3)),
            'name' => 'Point Kasa',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'CR-'.Str::upper(Str::random(5)),
            'name' => 'Tahsilat Cari',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $openResponse = $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 500,
        ])->assertCreated();

        $sessionId = (int) $openResponse->json('data.id');

        $this->postJson("/api/customers/{$customer->id}/collections", [
            'method' => 'cash',
            'amount' => 200,
            'note' => 'Cari ödeme',
            'meta' => [
                'source' => 'point_collection',
                'pos_session_id' => $sessionId,
                'cashbox_id' => $cashbox->id,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('collection.method', 'cash')
            ->assertJsonPath('collection.amount', '200.00');

        $this->getJson('/api/pos/reports/day-end?pos_session_id='.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.summary.expected_cash', '700.00')
            ->assertJsonPath('data.totals_by_method.0.method', 'cash')
            ->assertJsonPath('data.totals_by_method.0.total_amount', '200.00');
    }

    public function test_point_day_end_includes_session_linked_customer_collection_without_source_tag(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'menu_permissions' => ['pos', 'pos-day-end', 'delivery-notes'],
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => 'CB-'.Str::upper(Str::random(3)),
            'name' => 'Point Kasa',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'CR-'.Str::upper(Str::random(5)),
            'name' => 'Tahsilat Cari',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $openResponse = $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 100,
        ])->assertCreated();

        $sessionId = (int) $openResponse->json('data.id');

        $this->postJson("/api/customers/{$customer->id}/collections", [
            'method' => 'cc',
            'amount' => 75,
            'currency' => 'GEL',
            'reference_fields' => [
                'pos_bank' => 'yapi_kredi',
                'pos_payment_type' => 'pesin',
            ],
            'meta' => [
                'pos_session_id' => $sessionId,
                'cashbox_id' => $cashbox->id,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('collection.method', 'cc')
            ->assertJsonPath('collection.amount', '75.00');

        $this->getJson('/api/pos/reports/day-end?pos_session_id='.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.totals_by_method.1.method', 'card')
            ->assertJsonPath('data.totals_by_method.1.total_amount', '75.00')
            ->assertJsonPath('data.report_tables.card_collections.0.customer_name', 'Tahsilat Cari')
            ->assertJsonPath('data.report_tables.card_collections.0.amount', '75.00');
    }

    public function test_pos_menu_alone_does_not_allow_expenses_or_day_end_report(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'menu_permissions' => ['pos', 'delivery-notes'],
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => 'CB-'.Str::upper(Str::random(3)),
            'name' => 'Point Kasa',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $openResponse = $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 500,
        ])->assertCreated();

        $sessionId = (int) $openResponse->json('data.id');

        $this->postJson('/api/pos/expenses', [
            'pos_session_id' => $sessionId,
            'amount' => 125.50,
            'category' => 'Kargo',
        ])->assertForbidden();

        $this->getJson('/api/pos/reports/day-end?pos_session_id='.$sessionId)
            ->assertForbidden();
    }

    public function test_collection_for_exported_b2b_customer_is_queued_for_logo_after_send(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'b2b',
            'source_reference' => '1001',
            'sync_status' => 'synced',
            'code' => 'CR-'.Str::upper(Str::random(5)),
            'name' => 'Exported B2B Cari',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->postJson("/api/customers/{$customer->id}/collections", [
            'method' => 'cash',
            'amount' => 150,
            'note' => 'Cari ödeme',
        ])
            ->assertCreated()
            ->assertJsonPath('collection.sync_status', 'draft');

        $collection = Collection::query()->latest('id')->firstOrFail();

        $this->postJson("/api/customers/{$customer->id}/collections/{$collection->id}/send")
            ->assertOk()
            ->assertJsonPath('collection.sync_status', 'pending')
            ->assertJsonPath('message', 'Tahsilat gönderim kuyruğuna alındı.');
    }

    public function test_salesperson_collection_is_exported_with_matching_logo_cashbox(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-SP-'.Str::upper(Str::random(4)),
            'name' => 'Salesperson Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('salesperson', $dealer);
        $user->forceFill([
            'name' => 'Ahmet',
            'logo_cashbox_code' => '100.02.003',
            'logo_cashbox_name' => 'AHMET ARAÇ KASASI',
        ])->save();

        $cashbox = Cashbox::query()->create([
            'code' => '100.02.003',
            'name' => 'AHMET ARAÇ KASASI',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'salesperson_user_id' => $user->id,
            'source_system' => 'b2b',
            'source_reference' => '120-25-003',
            'sync_status' => 'synced',
            'code' => '120-25-003',
            'name' => 'Logo Plasiyer Cari',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->postJson("/api/customers/{$customer->id}/collections", [
            'method' => 'cash',
            'amount' => 5000,
            'note' => 'B2B plasiyer tahsilatı',
        ])
            ->assertCreated()
            ->assertJsonPath('collection.sync_status', 'draft')
            ->assertJsonPath('collection.meta.cashbox_id', $cashbox->id)
            ->assertJsonPath('collection.meta.cashbox.code', '100.02.003');

        $collection = Collection::query()->latest('id')->firstOrFail();

        $this->assertSame($cashbox->id, $collection->meta['cashbox_id']);

        $this->postJson("/api/customers/{$customer->id}/collections/{$collection->id}/send")
            ->assertOk()
            ->assertJsonPath('collection.sync_status', 'pending');

        $ledgerEntry = LedgerEntry::query()
            ->where('collection_id', $collection->id)
            ->firstOrFail();

        $this->assertSame($cashbox->id, $ledgerEntry->meta['cashbox_id']);

        config(['integrations.logo.collection_sync_key' => 'test-sync-key']);

        $this
            ->withHeader('X-Integration-Key', 'test-sync-key')
            ->getJson('/api/integrations/logo/collections/pending?limit=10')
            ->assertOk()
            ->assertJsonPath('records.0.collection_id', $collection->id)
            ->assertJsonPath('records.0.cashbox_id', $cashbox->id)
            ->assertJsonPath('records.0.cashbox_code', '100.02.003')
            ->assertJsonPath('records.0.cashbox_name', 'AHMET ARAÇ KASASI');
    }

    public function test_pos_sale_payment_for_exported_customer_is_marked_pending_for_logo_export_with_cashbox(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $cashbox = Cashbox::query()->create([
            'code' => '100.01.002',
            'name' => 'Ahmet Arac Kasasi',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'source_system' => 'b2b',
            'source_reference' => '120-25-002',
            'sync_status' => 'synced',
            'code' => '120-25-002',
            'name' => 'Logo Cari',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function (): Product {
            return Product::query()->create([
                'sku' => 'POS-TEST-001',
                'name' => 'POS Test Product',
                'unit' => 'adet',
                'vat_rate' => 0,
                'is_active' => true,
            ]);
        });

        DB::table('stock_summary')->insert([
            'product_id' => $product->id,
            'available_total' => 5,
            'reserved_total' => 0,
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $openResponse = $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 0,
        ])->assertCreated();

        $sessionId = (int) $openResponse->json('data.id');

        $this->postJson('/api/pos/sales', [
            'pos_session_id' => $sessionId,
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'document_type' => 'delivery',
            'receipt_no' => 'POS-EXPORT-001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 1,
                    'unit_price' => 100,
                    'vat_rate' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 100,
                    'meta_json' => [
                        'cash_received' => 100,
                    ],
                ],
            ],
        ])->assertCreated();

        $sale = PosSale::query()->latest('id')->firstOrFail();
        $collection = Collection::query()->latest('id')->firstOrFail();

        $this->assertSame('b2b', $collection->source_system);
        $this->assertSame('pending', $collection->sync_status);
        $this->assertSame('GEL', $collection->currency);
        $this->assertSame('pos_sale', $collection->meta['source']);
        $this->assertSame($sessionId, $collection->meta['pos_session_id']);
        $this->assertSame($cashbox->id, $collection->meta['cashbox_id']);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'type' => 'invoice',
            'currency' => 'GEL',
            'reference_no' => 'POS-EXPORT-001',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'type' => 'payment',
            'currency' => 'GEL',
            'reference_no' => 'POS-EXPORT-001',
        ]);

        $this->assertDatabaseHas('integration_sync_events', [
            'domain' => 'collections-write',
            'entity_type' => Collection::class,
            'entity_id' => $collection->id,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('integration_sync_states', [
            'domain' => 'pos-sales',
            'entity_type' => PosSale::class,
            'entity_id' => $sale->id,
            'status' => 'queued',
        ]);

        config(['integrations.logo.collection_sync_key' => 'test-sync-key']);

        $this
            ->withHeader('X-Integration-Key', 'test-sync-key')
            ->getJson('/api/integrations/logo/collections/pending?limit=10')
            ->assertOk()
            ->assertJsonPath('records.0.collection_id', $collection->id)
            ->assertJsonPath('records.0.cashbox_code', '100.01.002');

        config(['integrations.logo.pos_sale_sync_key' => 'test-pos-sale-key']);

        $this
            ->withHeader('X-Integration-Key', 'test-pos-sale-key')
            ->getJson('/api/integrations/logo/pos-sales/pending?limit=10')
            ->assertOk()
            ->assertJsonPath('records.0.pos_sale_id', $sale->id)
            ->assertJsonPath('records.0.receipt_no', 'POS-EXPORT-001')
            ->assertJsonPath('records.0.currency', 'GEL')
            ->assertJsonPath('records.0.payments.0.currency', 'GEL')
            ->assertJsonPath('records.0.cashbox_code', '100.01.002')
            ->assertJsonPath('records.0.items.0.product_code', 'POS-TEST-001');
    }

    public function test_pos_sale_can_be_created_when_stock_summary_is_missing(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-POINT-'.Str::upper(Str::random(4)),
            'name' => 'Point Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $cashbox = Cashbox::query()->create([
            'code' => '100.01.002',
            'name' => 'Ahmet Arac Kasasi',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'POINT-NAKIT',
            'name' => 'Point Nakit Cari',
            'is_active' => true,
        ]);

        $product = Product::withoutEvents(function (): Product {
            return Product::query()->create([
                'sku' => 'POS-NOSTOCK-001',
                'name' => 'POS Missing Stock Product',
                'unit' => 'adet',
                'vat_rate' => 0,
                'is_active' => true,
            ]);
        });

        $this->actingAs($user);

        $openResponse = $this->postJson('/api/pos/sessions/open', [
            'cashbox_id' => $cashbox->id,
            'opening_cash' => 0,
        ])->assertCreated();

        $this->postJson('/api/pos/sales', [
            'pos_session_id' => (int) $openResponse->json('data.id'),
            'customer_id' => $customer->id,
            'sale_type' => 'cash',
            'document_type' => 'invoice',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 1,
                    'unit_price' => 100,
                    'vat_rate' => 0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 100,
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('stock_summary', [
            'product_id' => $product->id,
            'available_total' => -1,
        ]);

        $sale = PosSale::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('integration_sync_states', [
            'domain' => 'pos-sales',
            'entity_type' => PosSale::class,
            'entity_id' => $sale->id,
            'status' => 'queued',
        ]);

        config(['integrations.logo.pos_sale_sync_key' => 'test-pos-sale-key']);

        $this
            ->withHeader('X-Integration-Key', 'test-pos-sale-key')
            ->getJson('/api/integrations/logo/pos-sales/pending?limit=10')
            ->assertOk()
            ->assertJsonPath('records.0.pos_sale_id', $sale->id)
            ->assertJsonPath('records.0.document_type', 'invoice');
    }

    public function test_customer_collections_can_be_filtered_by_method_and_invoice_entries(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-COL-'.Str::upper(Str::random(4)),
            'name' => 'Collection Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'CR-'.Str::upper(Str::random(5)),
            'name' => 'Filtre Cari',
            'is_active' => true,
        ]);

        Collection::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'date' => '2026-06-01',
            'collection_date' => '2026-06-01',
            'method' => 'cash',
            'amount' => 100,
            'currency' => 'TRY',
            'note' => 'Nakit tahsilat',
        ]);

        Collection::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'date' => '2026-06-02',
            'collection_date' => '2026-06-02',
            'method' => 'cc',
            'amount' => 75,
            'currency' => 'TRY',
            'reference_fields' => [
                'collection_channel' => 'factory',
            ],
            'note' => 'Fabrika kart çekimi',
        ]);

        LedgerEntry::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'date' => '2026-06-02',
            'entry_date' => '2026-06-02',
            'type' => 'invoice',
            'entry_type' => 'debit',
            'debit' => 250,
            'amount' => 250,
            'currency' => 'TRY',
            'reference_no' => 'INV-001',
            'description' => 'Fatura hareketi',
        ]);

        $this->actingAs($user);

        $this->getJson("/api/customers/{$customer->id}/collections?method=cash")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.method', 'cash')
            ->assertJsonPath('data.0.amount', '100.00')
            ->assertJsonFragment([
                'method' => 'cash',
                'count' => 1,
                'total_amount' => '100.00',
            ]);

        $this->getJson("/api/customers/{$customer->id}/collections?method=factory_cc")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.method', 'cc')
            ->assertJsonPath('data.0.reference_fields.collection_channel', 'factory')
            ->assertJsonFragment([
                'method' => 'factory_cc',
                'count' => 1,
                'total_amount' => '75.00',
            ]);

        $this->getJson("/api/customers/{$customer->id}/collections?method=invoice")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.record_type', 'invoice')
            ->assertJsonPath('data.0.method', 'invoice')
            ->assertJsonPath('data.0.amount', '250.00')
            ->assertJsonFragment([
                'method' => 'invoice',
                'count' => 1,
                'total_amount' => '250.00',
            ]);

        $this->getJson("/api/customers/{$customer->id}/collections")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_point_user_can_update_and_delete_draft_customer_collections(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-COL-EDIT-'.Str::upper(Str::random(4)),
            'name' => 'Collection Edit Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'EDIT-COL-'.Str::upper(Str::random(4)),
            'name' => 'Edit Collection Customer',
            'is_active' => true,
        ]);

        $collection = Collection::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'source_system' => 'b2b',
            'sync_status' => 'draft',
            'date' => '2026-06-01',
            'collection_date' => '2026-06-01',
            'method' => 'cash',
            'amount' => 100,
            'currency' => 'TRY',
            'note' => 'Eski not',
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/customers/{$customer->id}/collections/{$collection->id}", [
            'method' => 'transfer',
            'amount' => 125.50,
            'currency' => 'TRY',
            'date' => '2026-06-05',
            'note' => 'Güncel not',
        ])
            ->assertOk()
            ->assertJsonPath('collection.method', 'transfer')
            ->assertJsonPath('collection.amount', '125.50')
            ->assertJsonPath('collection.note', 'Güncel not');

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'method' => 'transfer',
            'amount' => '125.50',
            'note' => 'Güncel not',
        ]);

        $this->deleteJson("/api/customers/{$customer->id}/collections/{$collection->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('collections', [
            'id' => $collection->id,
        ]);
    }

    public function test_point_user_can_update_and_delete_synced_b2b_customer_collections(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-COL-SYNC-'.Str::upper(Str::random(4)),
            'name' => 'Collection Synced Edit Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'SYNC-COL-'.Str::upper(Str::random(4)),
            'name' => 'Synced Collection Customer',
            'is_active' => true,
        ]);

        $collection = Collection::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'source_system' => 'b2b',
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'date' => '2026-06-01',
            'collection_date' => '2026-06-01',
            'method' => 'cash',
            'amount' => 588.24,
            'currency' => 'GEL',
            'note' => 'Gönderilmiş not',
        ]);

        LedgerEntry::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'collection_id' => $collection->id,
            'date' => '2026-06-01',
            'entry_date' => '2026-06-01',
            'type' => 'payment',
            'entry_type' => 'credit',
            'credit' => 588.24,
            'amount' => 588.24,
            'currency' => 'GEL',
            'description' => 'Gönderilmiş not',
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/customers/{$customer->id}/collections/{$collection->id}", [
            'method' => 'cash',
            'amount' => 169.51,
            'currency' => 'GEL',
            'date' => '2026-06-09',
            'note' => 'Düzeltilmiş tahsilat',
        ])
            ->assertOk()
            ->assertJsonPath('collection.sync_status', 'draft')
            ->assertJsonPath('collection.amount', '169.51')
            ->assertJsonPath('collection.note', 'Düzeltilmiş tahsilat');

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'sync_status' => 'draft',
            'amount' => '169.51',
            'note' => 'Düzeltilmiş tahsilat',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'collection_id' => $collection->id,
            'credit' => '169.51',
            'amount' => '169.51',
            'description' => 'Düzeltilmiş tahsilat',
        ]);

        $this->deleteJson("/api/customers/{$customer->id}/collections/{$collection->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('collections', [
            'id' => $collection->id,
        ]);
        $this->assertDatabaseMissing('ledger_entries', [
            'collection_id' => $collection->id,
        ]);
    }

    public function test_batum_customer_collections_display_try_amounts_as_lari(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-BATUM-COL-'.Str::upper(Str::random(4)),
            'name' => 'Batum Collection Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'branch_code' => 'BATUM',
            'region_code' => 'BATUM',
        ])->save();

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => 'BATUM-COL-'.Str::upper(Str::random(4)),
            'name' => 'Batum Tahsilat Cari',
            'is_active' => true,
        ]);

        Collection::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'date' => '2026-06-01',
            'collection_date' => '2026-06-01',
            'method' => 'cash',
            'amount' => 170,
            'currency' => 'TRY',
        ]);

        LedgerEntry::query()->create([
            'dealer_id' => $dealer->id,
            'customer_id' => $customer->id,
            'date' => '2026-06-02',
            'entry_date' => '2026-06-02',
            'type' => 'invoice',
            'entry_type' => 'debit',
            'debit' => 340,
            'amount' => 340,
            'currency' => 'TRY',
        ]);

        $this->actingAs($user);

        $this->getJson("/api/customers/{$customer->id}/collections?method=cash")
            ->assertOk()
            ->assertJsonPath('data.0.amount', '10.00')
            ->assertJsonPath('data.0.currency', 'GEL')
            ->assertJsonFragment([
                'method' => 'cash',
                'count' => 1,
                'total_amount' => '10.00',
            ]);

        $this->getJson("/api/customers/{$customer->id}/collections?method=invoice")
            ->assertOk()
            ->assertJsonPath('data.0.amount', '20.00')
            ->assertJsonPath('data.0.currency', 'GEL')
            ->assertJsonFragment([
                'method' => 'invoice',
                'count' => 1,
                'total_amount' => '20.00',
            ]);
    }

    public function test_batum_branch_point_user_can_collect_batum_city_customer(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-BATUM-AUTH-'.Str::upper(Str::random(4)),
            'name' => 'Batum Auth Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'customer_scope' => 'branch',
            'branch_code' => 'BATUM',
            'region_code' => 'BATUM',
        ])->save();

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => '120-00-002',
            'name' => 'Batum Perakende Kredi Karti Satis',
            'city' => 'BATUMI',
            'district' => 'BATUMI',
            'source_system' => 'logo',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->postJson("/api/customers/{$customer->id}/collections", [
            'method' => 'cash',
            'amount' => 10,
            'currency' => 'GEL',
            'date' => '2026-06-05',
        ])
            ->assertCreated()
            ->assertJsonPath('collection.method', 'cash')
            ->assertJsonPath('collection.amount', '10.00');
    }

    public function test_batum_branch_point_user_can_select_georgian_bank_for_physical_pos_collection(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-BATUM-POS-'.Str::upper(Str::random(4)),
            'name' => 'Batum Pos Dealer',
            'is_active' => true,
        ]);

        $user = $this->createUserWithRole('point', $dealer);
        $user->forceFill([
            'customer_scope' => 'branch',
            'branch_code' => 'BATUM',
            'region_code' => 'BATUM',
        ])->save();

        $customer = Customer::query()->create([
            'dealer_id' => $dealer->id,
            'code' => '120-00-003',
            'name' => 'Batum Depo Siparis',
            'city' => 'BATUMI',
            'district' => 'BATUMI',
            'source_system' => 'logo',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $this->postJson("/api/customers/{$customer->id}/collections", [
            'method' => 'cc',
            'amount' => 25,
            'currency' => 'GEL',
            'date' => '2026-06-09',
            'reference_fields' => [
                'pos_bank' => 'tbc_bank',
                'pos_payment_type' => 'pesin',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('collection.method', 'cc')
            ->assertJsonPath('collection.reference_fields.pos_bank', 'tbc_bank');
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
}
