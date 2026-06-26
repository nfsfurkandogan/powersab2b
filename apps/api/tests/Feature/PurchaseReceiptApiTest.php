<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\IntegrationSyncState;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.logo.purchase_receipt_sync_key' => 'purchase-sync-key',
        ]);
    }

    public function test_purchase_receipt_store_queues_logo_export(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-MK-001',
            'name' => 'Mal Kabul Dealer',
            'is_active' => true,
        ]);
        $user = $this->createUserWithRole('dealer_admin', $dealer);
        $this->actingAs($user);

        $response = $this->postJson('/api/purchase-receipts', [
            'document_no' => 'IRS-000123',
            'supplier_name' => 'GO Wings Tedarikçi',
            'warehouse_code' => '2',
            'warehouse_name' => 'Logo Ambar 2',
            'received_at' => '2026-06-23',
            'note' => 'Test mal kabul',
            'items' => [
                [
                    'product_code' => 'CS-001',
                    'product_name' => 'Filtre Test',
                    'expected_quantity' => 4,
                    'accepted_quantity' => 3,
                    'note' => '1 eksik',
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.document_no', 'IRS-000123')
            ->assertJsonPath('data.items.0.product_code', 'CS-001')
            ->assertJsonPath('data.logo_sync_status', 'queued');

        $receiptId = (int) $response->json('data.id');

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receiptId,
            'dealer_id' => $dealer->id,
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('integration_sync_states', [
            'system' => 'logo',
            'domain' => 'purchase-receipts',
            'direction' => 'outbound',
            'entity_type' => PurchaseReceipt::class,
            'entity_id' => $receiptId,
            'status' => 'queued',
        ]);
    }

    public function test_logo_purchase_receipt_pending_and_acknowledge(): void
    {
        $dealer = Dealer::query()->create([
            'code' => 'DLR-MK-ACK',
            'name' => 'Mal Kabul Ack Dealer',
            'is_active' => true,
        ]);
        $user = $this->createUserWithRole('admin', $dealer);

        $receipt = PurchaseReceipt::query()->create([
            'dealer_id' => $dealer->id,
            'created_by' => $user->id,
            'receipt_no' => 'MK-ACK-001',
            'document_no' => 'IRS-ACK',
            'supplier_name' => 'Ack Supplier',
            'warehouse_code' => '1',
            'warehouse_name' => 'Erzurum Depo',
            'received_at' => '2026-06-23',
            'status' => 'queued',
        ]);
        $receipt->items()->create([
            'product_code' => 'ACK-001',
            'product_name' => 'Ack Product',
            'expected_quantity' => 2,
            'accepted_quantity' => 2,
        ]);

        IntegrationSyncState::query()->create([
            'system' => 'logo',
            'domain' => 'purchase-receipts',
            'direction' => 'outbound',
            'entity_type' => PurchaseReceipt::class,
            'entity_id' => $receipt->id,
            'dealer_id' => $dealer->id,
            'status' => 'queued',
            'meta' => ['export_key' => 'B2B-PURCHASE-'.$receipt->id],
        ]);

        $this
            ->withHeader('X-Integration-Key', 'purchase-sync-key')
            ->getJson('/api/integrations/logo/purchase-receipts/pending?limit=10')
            ->assertOk()
            ->assertJsonPath('received', 1)
            ->assertJsonPath('records.0.purchase_receipt_id', $receipt->id)
            ->assertJsonPath('records.0.logo.document_type', 'purchase_receipt')
            ->assertJsonPath('records.0.items.0.product_code', 'ACK-001');

        $this
            ->withHeader('X-Integration-Key', 'purchase-sync-key')
            ->postJson('/api/integrations/logo/purchase-receipts/ack', [
                'records' => [
                    [
                        'purchase_receipt_id' => $receipt->id,
                        'status' => 'synced',
                        'external_ref' => 'LOGO-MK-001',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('summary.synced', 1);

        $receipt->refresh();

        $this->assertSame('synced', $receipt->status);
        $this->assertDatabaseHas('integration_sync_states', [
            'domain' => 'purchase-receipts',
            'entity_type' => PurchaseReceipt::class,
            'entity_id' => $receipt->id,
            'status' => 'synced',
            'external_ref' => 'LOGO-MK-001',
        ]);
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
            'menu_permissions' => ['extra'],
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }
}
