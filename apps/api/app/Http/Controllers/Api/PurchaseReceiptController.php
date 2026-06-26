<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseReceipt\StorePurchaseReceiptRequest;
use App\Http\Resources\PurchaseReceiptResource;
use App\Models\PurchaseReceipt;
use App\Services\Integrations\IntegrationSyncStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseReceiptController extends Controller
{
    public function store(
        StorePurchaseReceiptRequest $request,
        IntegrationSyncStateService $syncState
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();
        $dealerId = $user->dealer_id ?? ($validated['dealer_id'] ?? null);

        $receipt = DB::transaction(function () use ($validated, $user, $dealerId, $syncState): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->create([
                'dealer_id' => $dealerId,
                'created_by' => $user->id,
                'receipt_no' => $this->generateReceiptNo(),
                'document_no' => $this->nullableString($validated['document_no'] ?? null),
                'supplier_name' => $this->nullableString($validated['supplier_name'] ?? null),
                'warehouse_code' => $this->nullableString($validated['warehouse_code'] ?? null),
                'warehouse_name' => $this->nullableString($validated['warehouse_name'] ?? null),
                'received_at' => $validated['received_at'],
                'note' => $this->nullableString($validated['note'] ?? null),
                'status' => 'queued',
            ]);

            foreach ($validated['items'] as $item) {
                $receipt->items()->create([
                    'product_code' => $this->nullableString($item['product_code'] ?? null),
                    'product_name' => trim((string) $item['product_name']),
                    'expected_quantity' => (int) $item['expected_quantity'],
                    'accepted_quantity' => (int) $item['accepted_quantity'],
                    'note' => $this->nullableString($item['note'] ?? null),
                ]);
            }

            $syncState->record(
                system: 'logo',
                domain: 'purchase-receipts',
                direction: 'outbound',
                entity: $receipt,
                externalRef: null,
                status: 'queued',
                error: null,
                meta: [
                    'export_key' => 'B2B-PURCHASE-'.$receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'document_no' => $receipt->document_no,
                    'warehouse_code' => $receipt->warehouse_code,
                ],
                payload: [
                    'purchase_receipt_id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'document_no' => $receipt->document_no,
                    'status' => $receipt->status,
                ],
            );

            return $receipt->fresh(['items']);
        });

        return response()->json([
            'data' => new PurchaseReceiptResource($receipt),
            'message' => 'Mal kabul kaydi Logo kuyruğuna alindi.',
        ], 201);
    }

    private function generateReceiptNo(): string
    {
        do {
            $receiptNo = 'MK-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (PurchaseReceipt::query()->where('receipt_no', $receiptNo)->exists());

        return $receiptNo;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
