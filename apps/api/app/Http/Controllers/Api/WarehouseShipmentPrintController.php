<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WarehouseShipmentPrintController extends Controller
{
    public function packingSlip(Request $request, Shipment $shipment): Response
    {
        $shipment = $this->loadShipment($shipment);
        $this->ensureShipmentScope($request->user(), $shipment);

        $totals = [
            'ordered_qty_total' => 0,
            'shipped_qty_total' => 0,
            'remaining_qty_total' => 0,
            'sent_amount' => 0.0,
        ];

        foreach ($shipment->items as $item) {
            $ordered = (int) $item->ordered_qty;
            $shipped = (int) $item->shipped_qty;
            $remaining = max(0, $ordered - $shipped);

            $totals['ordered_qty_total'] += $ordered;
            $totals['shipped_qty_total'] += $shipped;
            $totals['remaining_qty_total'] += $remaining;
            $totals['sent_amount'] += (float) $item->line_total_shipped;
        }

        return response()->view('warehouse.prints.packing-slip', [
            'shipment' => $shipment,
            'totals' => $totals,
            'powersaLogoDataUri' => $this->powersaLogoDataUri(),
            'statusLabel' => $this->statusLabel((string) $shipment->status),
            'printedAt' => now(),
        ], HttpResponse::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function label(Request $request, Shipment $shipment): Response
    {
        $shipment = $this->loadShipment($shipment);
        $this->ensureShipmentScope($request->user(), $shipment);

        return response()->view('warehouse.prints.label', [
            'shipment' => $shipment,
        ], HttpResponse::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    private function loadShipment(Shipment $shipment): Shipment
    {
        return $shipment->loadMissing([
            'order.customer',
            'order.dealer',
            'order.user',
            'warehouse',
            'items.product.brand',
            'createdBy',
        ]);
    }

    private function powersaLogoDataUri(): ?string
    {
        $paths = [
            base_path('../web/public/brand/powersa-logo-tight.png'),
            base_path('../web/public/brand/powersa-logo.png'),
        ];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }

            $binary = file_get_contents($path);

            if ($binary === false) {
                continue;
            }

            $mime = mime_content_type($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        }

        return null;
    }

    private function statusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'draft' => 'Taslak',
            'picking' => 'Toplanıyor',
            'packed' => 'Hazırlandı',
            'shipped' => 'Sevk Edildi',
            'cancelled' => 'İptal',
            default => strtoupper($status),
        };
    }

    private function ensureShipmentScope(?User $user, Shipment $shipment): void
    {
        if (! $user instanceof User) {
            abort(HttpResponse::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        if ($user->hasRole('admin')) {
            return;
        }

        $dealerId = $shipment->order?->dealer_id;

        if ($dealerId === null || $user->dealer_id === null || (int) $dealerId !== (int) $user->dealer_id) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'Bu sevkiyat yazdirma yetkiniz yok.');
        }
    }
}
