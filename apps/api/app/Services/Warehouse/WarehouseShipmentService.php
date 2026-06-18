<?php

namespace App\Services\Warehouse;

use App\Models\IntegrationSyncState;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentScan;
use App\Models\StockMovement;
use App\Models\StockSummary;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Integrations\IntegrationSyncStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WarehouseShipmentService
{
    public function __construct(
        private readonly IntegrationSyncStateService $syncState
    ) {}

    public function createShipment(
        User $user,
        int $orderId,
        ?int $warehouseId = null,
        ?string $warehouseCode = null,
        ?string $warehouseName = null,
        ?int $assignedUserId = null
    ): Shipment {
        return DB::transaction(function () use ($user, $orderId, $warehouseId, $warehouseCode, $warehouseName, $assignedUserId): Shipment {
            $order = Order::query()
                ->with(['items'])
                ->lockForUpdate()
                ->find($orderId);

            if (! $order instanceof Order) {
                throw ValidationException::withMessages([
                    'order_id' => ['Siparis bulunamadi.'],
                ]);
            }

            $this->ensureOrderScope($user, $order);

            if ($order->status !== 'approved') {
                throw ValidationException::withMessages([
                    'order_id' => ['Sadece approved durumundaki siparisler sevkiyata hazirdir.'],
                ]);
            }

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_id' => ['Sipariste kalem bulunmuyor.'],
                ]);
            }

            $warehouse = $this->resolveWarehouse($warehouseId, $warehouseCode, $warehouseName);
            $assignedUser = $this->resolveAssignedWarehouseUser($assignedUserId, $user);

            $activeShipment = Shipment::query()
                ->where('order_id', $order->id)
                ->whereNotIn('status', ['cancelled'])
                ->latest('id')
                ->first();

            if ($activeShipment instanceof Shipment) {
                return $activeShipment->fresh([
                    'order.customer',
                    'warehouse',
                    'items.product',
                    'scans.scannedBy',
                ]);
            }

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'warehouse_id' => $warehouse->id,
                'shipment_no' => $this->generateShipmentNo(),
                'status' => 'draft',
                'created_by' => $assignedUser->id,
            ]);

            foreach ($order->items as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'ordered_qty' => (int) $item->quantity,
                    'shipped_qty' => 0,
                    'unit_price' => number_format((float) $item->unit_net_price, 2, '.', ''),
                    'vat_rate' => number_format((float) $item->tax_rate, 2, '.', ''),
                    'line_total_shipped' => 0,
                ]);
            }

            return $shipment->fresh([
                'order.customer',
                'warehouse',
                'items.product',
                'scans.scannedBy',
            ]);
        });
    }

    private function resolveWarehouse(?int $warehouseId, ?string $warehouseCode, ?string $warehouseName): Warehouse
    {
        $warehouse = null;

        if ($warehouseId !== null && $warehouseId > 0) {
            $warehouse = Warehouse::query()->find($warehouseId);
        }

        $code = trim((string) $warehouseCode);
        if (! $warehouse instanceof Warehouse && $code !== '') {
            $warehouse = Warehouse::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => trim((string) $warehouseName) !== '' ? trim((string) $warehouseName) : "Logo Ambar {$code}",
                    'is_active' => true,
                ]
            );
        }

        if (! $warehouse instanceof Warehouse || ! $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Depo bulunamadi veya aktif degil.'],
            ]);
        }

        return $warehouse;
    }

    private function resolveAssignedWarehouseUser(?int $assignedUserId, User $fallbackUser): User
    {
        if ($assignedUserId === null || $assignedUserId <= 0) {
            return $fallbackUser;
        }

        $assignedUser = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', 'warehouse'))
            ->find($assignedUserId);

        if (! $assignedUser instanceof User) {
            throw ValidationException::withMessages([
                'assigned_user_id' => ['Aktif depocu bulunamadi.'],
            ]);
        }

        if (! $fallbackUser->hasRole('admin') && $fallbackUser->dealer_id !== null) {
            $assignedDealerId = $assignedUser->dealer_id;
            if ($assignedDealerId !== null && (int) $assignedDealerId !== (int) $fallbackUser->dealer_id) {
                throw ValidationException::withMessages([
                    'assigned_user_id' => ['Bu depocu icin yetkiniz yok.'],
                ]);
            }
        }

        return $assignedUser;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function scanShipment(User $user, Shipment $shipment, array $payload): array
    {
        return DB::transaction(function () use ($user, $shipment, $payload): array {
            $model = Shipment::query()
                ->with([
                    'order.items',
                    'items.orderItem',
                    'items.product',
                    'warehouse',
                ])
                ->lockForUpdate()
                ->find($shipment->id);

            if (! $model instanceof Shipment) {
                throw ValidationException::withMessages([
                    'shipment' => ['Sevkiyat bulunamadi.'],
                ]);
            }

            $this->ensureShipmentScope($user, $model);

            if (in_array($model->status, ['cancelled', 'shipped', 'partially_shipped'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => ['Bu sevkiyat barkod okutmaya kapali.'],
                ]);
            }

            $barcode = trim((string) $payload['barcode']);
            $qty = max(1, (int) ($payload['qty'] ?? 1));

            $product = $this->findProductByBarcode($barcode);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages([
                    'barcode' => ['Barkod ile eslesen urun bulunamadi.'],
                ]);
            }

            $shipmentItem = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $shipmentItem instanceof ShipmentItem) {
                throw ValidationException::withMessages([
                    'barcode' => ['Okutulan urun sipariste bulunmuyor.'],
                ]);
            }

            $nextShippedQty = (int) $shipmentItem->shipped_qty + $qty;
            if ($nextShippedQty > (int) $shipmentItem->ordered_qty) {
                throw ValidationException::withMessages([
                    'qty' => ['Fazla okutma yapilamaz. Siparis miktari asildi.'],
                ]);
            }

            $shipmentItem->shipped_qty = $nextShippedQty;
            $shipmentItem->line_total_shipped = number_format(
                ((float) $shipmentItem->unit_price) * $nextShippedQty,
                2,
                '.',
                ''
            );
            $shipmentItem->save();

            ShipmentScan::create([
                'shipment_id' => $model->id,
                'product_id' => $product->id,
                'barcode' => $barcode,
                'qty' => $qty,
                'scanned_by' => $user->id,
                'scanned_at' => now(),
            ]);

            $hasRemaining = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->whereColumn('shipped_qty', '<', 'ordered_qty')
                ->exists();

            $newShipmentStatus = $hasRemaining ? 'picking' : 'packed';
            if ($model->status !== $newShipmentStatus) {
                $model->status = $newShipmentStatus;
                $model->save();
            }

            if ($newShipmentStatus === 'picking' && $model->order->status === 'approved') {
                $this->updateOrderStatus($model->order, 'picking', $user, 'Depo barkod okutma basladi.');
            }

            if ($newShipmentStatus === 'packed' && $model->order->status !== 'packed') {
                $this->updateOrderStatus($model->order, 'packed', $user, 'Tum kalemler okutuldu ve paketlendi.');
            }

            $state = $this->shipmentState($user, $model->fresh([
                'order.customer',
                'warehouse',
                'items.product',
                'scans.scannedBy',
            ]));

            return [
                ...$state,
                'message' => 'Barkod okutuldu.',
                'gonderilen_tutar' => $state['totals']['gonderilen_tutar'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function returnShipmentItem(User $user, Shipment $shipment, array $payload): array
    {
        return DB::transaction(function () use ($user, $shipment, $payload): array {
            $model = Shipment::query()
                ->with([
                    'order.items',
                    'items.orderItem',
                    'items.product',
                    'warehouse',
                ])
                ->lockForUpdate()
                ->find($shipment->id);

            if (! $model instanceof Shipment) {
                throw ValidationException::withMessages([
                    'shipment' => ['Sevkiyat bulunamadi.'],
                ]);
            }

            $this->ensureShipmentScope($user, $model);

            if (in_array($model->status, ['cancelled', 'shipped', 'partially_shipped'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => ['Bu sevkiyat geri almaya kapali.'],
                ]);
            }

            $shipmentItem = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->where('id', (int) $payload['item_id'])
                ->lockForUpdate()
                ->first();

            if (! $shipmentItem instanceof ShipmentItem) {
                throw ValidationException::withMessages([
                    'item_id' => ['Sevk satiri bulunamadi.'],
                ]);
            }

            $shippedQty = (int) $shipmentItem->shipped_qty;
            if ($shippedQty <= 0) {
                throw ValidationException::withMessages([
                    'qty' => ['Bu satirda geri alinacak sevk adedi yok.'],
                ]);
            }

            $qty = $payload['qty'] === null ? $shippedQty : max(1, (int) $payload['qty']);
            $returnQty = min($qty, $shippedQty);
            $nextShippedQty = max(0, $shippedQty - $returnQty);

            $shipmentItem->shipped_qty = $nextShippedQty;
            $shipmentItem->line_total_shipped = number_format(
                ((float) $shipmentItem->unit_price) * $nextShippedQty,
                2,
                '.',
                ''
            );
            $shipmentItem->save();

            $hasShipped = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->where('shipped_qty', '>', 0)
                ->exists();
            $hasRemaining = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->whereColumn('shipped_qty', '<', 'ordered_qty')
                ->exists();

            $newShipmentStatus = ! $hasShipped ? 'draft' : ($hasRemaining ? 'picking' : 'packed');
            if ($model->status !== $newShipmentStatus) {
                $model->status = $newShipmentStatus;
                $model->save();
            }

            if (! $hasShipped && $model->order->status !== 'approved') {
                $this->updateOrderStatus($model->order, 'approved', $user, 'Sevk edilen urunler geri alindi.');
            } elseif ($hasShipped && $hasRemaining && $model->order->status !== 'picking') {
                $this->updateOrderStatus($model->order, 'picking', $user, 'Sevk satiri geri alindi.');
            } elseif ($hasShipped && ! $hasRemaining && $model->order->status !== 'packed') {
                $this->updateOrderStatus($model->order, 'packed', $user, 'Tum kalemler okutuldu ve paketlendi.');
            }

            $state = $this->shipmentState($user, $model->fresh([
                'order.customer',
                'warehouse',
                'items.product',
                'scans.scannedBy',
            ]));

            return [
                ...$state,
                'message' => 'Sevk satiri geri alindi.',
                'gonderilen_tutar' => $state['totals']['gonderilen_tutar'],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteShipmentItem(User $user, Shipment $shipment, int $itemId): array
    {
        return DB::transaction(function () use ($user, $shipment, $itemId): array {
            $model = Shipment::query()
                ->with([
                    'order.items',
                    'items.orderItem',
                    'items.product',
                    'warehouse',
                ])
                ->lockForUpdate()
                ->find($shipment->id);

            if (! $model instanceof Shipment) {
                throw ValidationException::withMessages([
                    'shipment' => ['Sevkiyat bulunamadi.'],
                ]);
            }

            $this->ensureShipmentScope($user, $model);

            if (in_array($model->status, ['cancelled', 'shipped', 'partially_shipped'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => ['Bu sevkiyat kalem silmeye kapali.'],
                ]);
            }

            $shipmentItem = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->where('id', $itemId)
                ->lockForUpdate()
                ->first();

            if (! $shipmentItem instanceof ShipmentItem) {
                throw ValidationException::withMessages([
                    'item_id' => ['Sevkiyat kalemi bulunamadi.'],
                ]);
            }

            $itemCount = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->lockForUpdate()
                ->count();

            if ($itemCount <= 1) {
                throw ValidationException::withMessages([
                    'item_id' => ['Sevkiyatta en az bir kalem kalmalidir.'],
                ]);
            }

            ShipmentScan::query()
                ->where('shipment_id', $model->id)
                ->where('product_id', $shipmentItem->product_id)
                ->delete();

            $shipmentItem->delete();

            $hasShipped = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->where('shipped_qty', '>', 0)
                ->exists();
            $hasRemaining = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->whereColumn('shipped_qty', '<', 'ordered_qty')
                ->exists();

            $newShipmentStatus = ! $hasShipped ? 'draft' : ($hasRemaining ? 'picking' : 'packed');
            if ($model->status !== $newShipmentStatus) {
                $model->status = $newShipmentStatus;
                $model->save();
            }

            if (! $hasShipped && $model->order->status !== 'approved') {
                $this->updateOrderStatus($model->order, 'approved', $user, 'Sevkiyat kalemi silindi.');
            } elseif ($hasShipped && $hasRemaining && $model->order->status !== 'picking') {
                $this->updateOrderStatus($model->order, 'picking', $user, 'Sevkiyat kalemi silindi.');
            } elseif ($hasShipped && ! $hasRemaining && $model->order->status !== 'packed') {
                $this->updateOrderStatus($model->order, 'packed', $user, 'Kalan sevkiyat kalemleri paketlendi.');
            }

            $state = $this->shipmentState($user, $model->fresh([
                'order.customer',
                'warehouse',
                'items.product',
                'scans.scannedBy',
            ]));

            return [
                ...$state,
                'message' => 'Sevkiyat kalemi silindi.',
                'gonderilen_tutar' => $state['totals']['gonderilen_tutar'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function finalizeShipment(User $user, Shipment $shipment, array $payload): array
    {
        return DB::transaction(function () use ($user, $shipment, $payload): array {
            $model = Shipment::query()
                ->with([
                    'order.items',
                    'items.orderItem',
                    'items.product',
                    'warehouse',
                ])
                ->lockForUpdate()
                ->find($shipment->id);

            if (! $model instanceof Shipment) {
                throw ValidationException::withMessages([
                    'shipment' => ['Sevkiyat bulunamadi.'],
                ]);
            }

            $this->ensureShipmentScope($user, $model);

            if (in_array($model->status, ['cancelled', 'shipped', 'partially_shipped'], true)) {
                throw ValidationException::withMessages([
                    'shipment' => ['Bu sevkiyat finalize edilemez.'],
                ]);
            }

            $totalShippedInShipment = (int) $model->items->sum('shipped_qty');
            if ($totalShippedInShipment <= 0) {
                throw ValidationException::withMessages([
                    'shipment' => ['Finalize icin en az bir okutulmus urun olmalidir.'],
                ]);
            }

            foreach ($model->items as $item) {
                $qty = (int) $item->shipped_qty;
                if ($qty <= 0) {
                    continue;
                }

                $stock = StockSummary::query()
                    ->lockForUpdate()
                    ->find($item->product_id);

                if (! $stock instanceof StockSummary) {
                    throw ValidationException::withMessages([
                        'stock' => ["Stock summary bulunamadi (product_id={$item->product_id})."],
                    ]);
                }

                if ((int) $stock->available_total < $qty) {
                    throw ValidationException::withMessages([
                        'stock' => ["Yetersiz available stok (product_id={$item->product_id})."],
                    ]);
                }

                if ((int) $stock->reserved_total < $qty) {
                    throw ValidationException::withMessages([
                        'stock' => ["Yetersiz reserved stok (product_id={$item->product_id})."],
                    ]);
                }

                $stock->available_total = (int) $stock->available_total - $qty;
                $stock->reserved_total = (int) $stock->reserved_total - $qty;
                $stock->updated_at = now();
                $stock->save();

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'type' => 'out',
                    'source' => 'shipment',
                    'source_id' => $model->id,
                    'qty' => number_format((float) $qty, 3, '.', ''),
                    'created_at' => now(),
                ]);

                $orderItem = OrderItem::query()
                    ->lockForUpdate()
                    ->find($item->order_item_id);

                if (! $orderItem instanceof OrderItem) {
                    throw ValidationException::withMessages([
                        'order_item_id' => ["Order kalemi bulunamadi (id={$item->order_item_id})."],
                    ]);
                }

                $orderItem->shipped_qty = min(
                    (int) $orderItem->quantity,
                    (int) $orderItem->shipped_qty + $qty
                );
                $orderItem->save();
            }

            $hasRemainingInShipment = ShipmentItem::query()
                ->where('shipment_id', $model->id)
                ->whereColumn('shipped_qty', '<', 'ordered_qty')
                ->exists();

            $model->status = $hasRemainingInShipment ? 'partially_shipped' : 'shipped';
            $model->carrier_name = $payload['carrier_name'] ?? $model->carrier_name;
            $model->tracking_no = $payload['tracking_no'] ?? $model->tracking_no;
            $model->note = $payload['note'] ?? $model->note;
            $model->shipped_at = now();
            $model->save();

            $this->syncOrderShipmentStatus($model->order, $user, 'Sevkiyat finalize edildi.');

            $this->syncState->record(
                system: 'logo',
                domain: 'warehouse-shipments',
                direction: 'outbound',
                entity: $model,
                externalRef: null,
                status: 'queued',
                error: null,
                meta: [
                    'export_key' => 'B2B-SHIP-'.$model->id,
                    'shipment_no' => $model->shipment_no,
                    'order_id' => $model->order_id,
                    'order_no' => $model->order?->order_no,
                    'warehouse_code' => $model->warehouse?->code,
                ],
                payload: [
                    'shipment_id' => $model->id,
                    'shipment_no' => $model->shipment_no,
                    'order_id' => $model->order_id,
                    'status' => $model->status,
                ],
            );

            $state = $this->shipmentState($user, $model->fresh([
                'order.customer',
                'warehouse',
                'items.product',
                'scans.scannedBy',
            ]));

            return [
                ...$state,
                'message' => 'Sevkiyat finalize edildi.',
                'gonderilen_tutar' => $state['totals']['gonderilen_tutar'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancelShipment(User $user, Shipment $shipment, array $payload = []): array
    {
        return DB::transaction(function () use ($user, $shipment, $payload): array {
            $model = Shipment::query()
                ->with([
                    'order.items',
                    'items.orderItem',
                    'items.product',
                    'warehouse',
                ])
                ->lockForUpdate()
                ->find($shipment->id);

            if (! $model instanceof Shipment) {
                throw ValidationException::withMessages([
                    'shipment' => ['Sevkiyat bulunamadi.'],
                ]);
            }

            $this->ensureShipmentScope($user, $model);

            if ($model->status === 'cancelled') {
                return $this->shipmentState($user, $model->fresh([
                    'order.customer',
                    'warehouse',
                    'items.product',
                    'scans.scannedBy',
                ]));
            }

            $wasFinalized = in_array($model->status, ['shipped', 'partially_shipped'], true);

            if ($wasFinalized) {
                foreach ($model->items as $item) {
                    $qty = (int) $item->shipped_qty;
                    if ($qty <= 0) {
                        continue;
                    }

                    $stock = StockSummary::query()
                        ->lockForUpdate()
                        ->find($item->product_id);

                    if (! $stock instanceof StockSummary) {
                        throw ValidationException::withMessages([
                            'stock' => ["Stock summary bulunamadi (product_id={$item->product_id})."],
                        ]);
                    }

                    $stock->available_total = (int) $stock->available_total + $qty;
                    $stock->reserved_total = (int) $stock->reserved_total + $qty;
                    $stock->updated_at = now();
                    $stock->save();

                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'type' => 'in',
                        'source' => 'shipment',
                        'source_id' => $model->id,
                        'qty' => number_format((float) $qty, 3, '.', ''),
                        'created_at' => now(),
                    ]);

                    $orderItem = OrderItem::query()
                        ->lockForUpdate()
                        ->find($item->order_item_id);

                    if ($orderItem instanceof OrderItem) {
                        $orderItem->shipped_qty = max(0, (int) $orderItem->shipped_qty - $qty);
                        $orderItem->save();
                    }
                }
            }

            $model->status = 'cancelled';
            $model->note = isset($payload['note']) && is_string($payload['note'])
                ? trim((string) $payload['note'])
                : $model->note;
            $model->save();

            $this->syncOrderShipmentStatus($model->order, $user, 'Sevkiyat iptal edildi.');

            return [
                ...$this->shipmentState($user, $model->fresh([
                    'order.customer',
                    'warehouse',
                    'items.product',
                    'scans.scannedBy',
                ])),
                'message' => 'Sevkiyat iptal edildi.',
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function shipmentState(User $user, Shipment $shipment): array
    {
        $shipment->loadMissing([
            'order.customer',
            'warehouse',
            'items.product.stockSummary',
            'scans.scannedBy',
        ]);

        $this->ensureShipmentScope($user, $shipment);
        $logoSyncState = $this->logoSyncState('warehouse-shipments', Shipment::class, (int) $shipment->id);
        $customer = $shipment->order?->customer;
        $customerMeta = is_array($customer?->meta) ? $customer->meta : [];

        $remainingItems = [];
        $shippedItems = [];

        $orderedQtyTotal = 0;
        $shippedQtyTotal = 0;
        $sentAmount = 0.0;

        foreach ($shipment->items as $item) {
            $orderedQty = (int) $item->ordered_qty;
            $shippedQty = (int) $item->shipped_qty;
            $remainingQty = max(0, $orderedQty - $shippedQty);
            $lineTotalShipped = (float) $item->line_total_shipped;

            $orderedQtyTotal += $orderedQty;
            $shippedQtyTotal += $shippedQty;
            $sentAmount += $lineTotalShipped;

            $payload = [
                'id' => $item->id,
                'order_item_id' => $item->order_item_id,
                'product_id' => $item->product_id,
                'sku' => $item->product?->sku,
                'oem' => $item->product?->oem_code,
                'name' => $item->product?->name,
                'shelf_address' => $this->resolveShelfAddress((array) ($item->product?->meta ?? [])),
                'ordered_qty' => $orderedQty,
                'shipped_qty' => $shippedQty,
                'remaining_qty' => $remainingQty,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'vat_rate' => number_format((float) $item->vat_rate, 2, '.', ''),
                'line_total_shipped' => number_format($lineTotalShipped, 2, '.', ''),
                'logo_stock' => [
                    'available_total' => (int) ($item->product?->stockSummary?->available_total ?? 0),
                    'reserved_total' => (int) ($item->product?->stockSummary?->reserved_total ?? 0),
                    'updated_at' => $item->product?->stockSummary?->updated_at?->toIso8601String(),
                ],
            ];

            if ($remainingQty > 0) {
                $remainingItems[] = $payload;
            }

            if ($shippedQty > 0) {
                $shippedItems[] = $payload;
            }
        }

        $remainingQtyTotal = max(0, $orderedQtyTotal - $shippedQtyTotal);

        return [
            'shipment' => [
                'id' => $shipment->id,
                'shipment_no' => $shipment->shipment_no,
                'status' => $shipment->status,
                'order_id' => $shipment->order_id,
                'warehouse_id' => $shipment->warehouse_id,
                'carrier_name' => $shipment->carrier_name,
                'tracking_no' => $shipment->tracking_no,
                'note' => $shipment->note,
                'shipped_at' => $shipment->shipped_at,
                'logo_sync_status' => $logoSyncState?->status,
                'logo_sync_error' => $logoSyncState?->last_error,
                'logo_external_ref' => $logoSyncState?->external_ref,
                'logo_last_synced_at' => $logoSyncState?->last_synced_at,
                'created_at' => $shipment->created_at,
                'updated_at' => $shipment->updated_at,
                'order' => [
                    'id' => $shipment->order?->id,
                    'order_no' => $shipment->order?->order_no,
                    'status' => $shipment->order?->status,
                    'customer' => [
                        'id' => $customer?->id,
                        'code' => $customer?->code,
                        'title' => $customer?->name,
                        'city' => $this->resolveCustomerLocation($customer?->city, $customerMeta, 'city'),
                        'district' => $this->resolveCustomerLocation($customer?->district, $customerMeta, 'district'),
                        'address' => $this->resolveCustomerAddress($customerMeta),
                        'phone' => $this->resolveCustomerPhone($customer?->phone, $customerMeta),
                    ],
                ],
                'warehouse' => [
                    'id' => $shipment->warehouse?->id,
                    'code' => $shipment->warehouse?->code,
                    'name' => $shipment->warehouse?->name,
                ],
            ],
            'remaining_items' => array_values($remainingItems),
            'shipped_items' => array_values($shippedItems),
            'totals' => [
                'ordered_qty_total' => $orderedQtyTotal,
                'shipped_qty_total' => $shippedQtyTotal,
                'remaining_qty_total' => $remainingQtyTotal,
                'sent_amount' => number_format($sentAmount, 2, '.', ''),
                'gonderilen_tutar' => number_format($sentAmount, 2, '.', ''),
            ],
            'recent_scans' => $shipment->scans
                ->sortByDesc('scanned_at')
                ->take(50)
                ->values()
                ->map(fn (ShipmentScan $scan) => [
                    'id' => $scan->id,
                    'product_id' => $scan->product_id,
                    'barcode' => $scan->barcode,
                    'qty' => (int) $scan->qty,
                    'scanned_at' => $scan->scanned_at,
                    'scanned_by' => [
                        'id' => $scan->scannedBy?->id,
                        'name' => $scan->scannedBy?->name,
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveCustomerPhone(?string $phone, array $meta): ?string
    {
        $current = trim((string) $phone);
        if ($current !== '') {
            return $current;
        }

        foreach ([
            'integrations.logo.payload.raw.TELNRS1',
            'integrations.logo.payload.raw.TELNR1',
            'integrations.logo.payload.raw.PHONE',
            'integrations.logo.payload.raw.PHONE1',
            'integrations.logo.payload.raw.PHONE_1',
            'integrations.logo.payload.raw.TELEFON',
            'integrations.logo.payload.raw.TELEFON1',
            'integrations.logo.payload.raw.GSM',
            'integrations.logo.payload.raw.CEPTEL',
            'integrations.logo.payload.phone',
            'integrations.logo.payload.phone_1',
            'integrations.logo.payload.telephone',
            'integrations.logo.payload.mobile_phone',
        ] as $path) {
            $candidate = trim((string) data_get($meta, $path, ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveCustomerLocation(?string $value, array $meta, string $field): ?string
    {
        $current = trim((string) $value);
        if ($current !== '') {
            return $current;
        }

        $paths = $field === 'city'
            ? [
                'integrations.logo.payload.raw.CITY',
                'integrations.logo.payload.raw.IL',
                'integrations.logo.payload.raw.PROVINCE',
                'integrations.logo.payload.city',
                'integrations.logo.payload.province',
            ]
            : [
                'integrations.logo.payload.raw.TOWN',
                'integrations.logo.payload.raw.DISTRICT',
                'integrations.logo.payload.raw.ILCE',
                'integrations.logo.payload.raw.İLÇE',
                'integrations.logo.payload.raw.COUNTY',
                'integrations.logo.payload.district',
                'integrations.logo.payload.town',
            ];

        foreach ($paths as $path) {
            $candidate = trim((string) data_get($meta, $path, ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveCustomerAddress(array $meta): ?string
    {
        foreach ([
            'address',
            'full_address',
            'integrations.logo.payload.address',
            'integrations.logo.payload.full_address',
            'integrations.logo.payload.raw.ADDR1',
            'integrations.logo.payload.raw.ADDRESS',
            'integrations.logo.payload.raw.ADRES',
        ] as $path) {
            $candidate = trim((string) data_get($meta, $path, ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function logoSyncState(string $domain, string $entityType, int $entityId): ?IntegrationSyncState
    {
        return IntegrationSyncState::query()
            ->where('system', 'logo')
            ->where('domain', $domain)
            ->where('direction', 'outbound')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->latest('id')
            ->first();
    }

    private function ensureShipmentScope(User $user, Shipment $shipment): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if (! $shipment->relationLoaded('order')) {
            $shipment->load('order');
        }

        if ($user->dealer_id === null || (int) $user->dealer_id !== (int) $shipment->order?->dealer_id) {
            abort(Response::HTTP_FORBIDDEN, 'Bu sevkiyata erisim yetkiniz yok.');
        }
    }

    private function ensureOrderScope(User $user, Order $order): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        if ($user->dealer_id === null || (int) $user->dealer_id !== (int) $order->dealer_id) {
            abort(Response::HTTP_FORBIDDEN, 'Bu siparis icin sevkiyat yetkiniz yok.');
        }
    }

    private function syncOrderShipmentStatus(Order $order, User $user, string $note): void
    {
        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->get();

        $orderedTotal = (int) $orderItems->sum('quantity');
        $shippedTotal = (int) $orderItems->sum('shipped_qty');

        $nextStatus = 'approved';

        if ($shippedTotal > 0 && $shippedTotal < $orderedTotal) {
            $nextStatus = 'partially_shipped';
        }

        if ($orderedTotal > 0 && $shippedTotal >= $orderedTotal) {
            $nextStatus = 'shipped';
        }

        $this->updateOrderStatus($order, $nextStatus, $user, $note);
    }

    private function updateOrderStatus(Order $order, string $status, User $user, string $note): void
    {
        if ($order->status === $status) {
            return;
        }

        $order->status = $status;
        $order->save();

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $status,
            'changed_by_user_id' => $user->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function findProductByBarcode(string $barcode): ?Product
    {
        $barcode = trim($barcode);

        $direct = Product::query()
            ->where('sku', $barcode)
            ->orWhere('oem_code', $barcode)
            ->first();

        if ($direct instanceof Product) {
            return $direct;
        }

        try {
            return Product::query()
                ->where('meta->barcode', $barcode)
                ->orWhereJsonContains('meta->barcodes', $barcode)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveShelfAddress(array $meta): ?string
    {
        foreach ([
            'shelf_address',
            'raf_address',
            'raf_adresi',
            'raf_bilgisi',
            'raf_bilgileri',
            'shelf',
            'raf',
            'location',
            'location_code',
            'integrations.logo.payload.shelf_address',
            'integrations.logo.payload.shelfaddress',
            'integrations.logo.payload.shelf_addr',
            'integrations.logo.payload.raf_address',
            'integrations.logo.payload.raf_adresi',
            'integrations.logo.payload.rafadresi',
            'integrations.logo.payload.raf_bilgisi',
            'integrations.logo.payload.rafbilgisi',
            'integrations.logo.payload.raf_bilgileri',
            'integrations.logo.payload.rafbilgileri',
            'integrations.logo.payload.shelf',
            'integrations.logo.payload.raf',
            'integrations.logo.payload.location',
            'integrations.logo.payload.location_code',
            'integrations.logo.payload.raw.SHELF_ADDRESS',
            'integrations.logo.payload.raw.SHELFADDRESS',
            'integrations.logo.payload.raw.SHELF_ADDR',
            'integrations.logo.payload.raw.RAF_ADDRESS',
            'integrations.logo.payload.raw.RAF_ADRESI',
            'integrations.logo.payload.raw.RAFADRESI',
            'integrations.logo.payload.raw.RAF_BILGISI',
            'integrations.logo.payload.raw.RAFBILGISI',
            'integrations.logo.payload.raw.RAF_BILGILERI',
            'integrations.logo.payload.raw.RAFBILGILERI',
            'integrations.logo.payload.raw.SHELF',
            'integrations.logo.payload.raw.RAF',
            'integrations.logo.payload.raw.LOCATION',
            'integrations.logo.payload.raw.LOCATION_CODE',
            'integrations.logo.payload.raw.LOCATIONCODE',
        ] as $path) {
            $value = data_get($meta, $path);

            if (is_scalar($value)) {
                $normalized = trim((string) $value);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function generateShipmentNo(): string
    {
        do {
            $candidate = 'SHP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
            $exists = Shipment::query()->where('shipment_no', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
