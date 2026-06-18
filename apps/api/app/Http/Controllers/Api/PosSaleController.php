<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CancelPosSaleRequest;
use App\Http\Requests\Pos\CreatePosSaleRequest;
use App\Http\Requests\Pos\ListPosSalesRequest;
use App\Http\Requests\Pos\UpdatePosSaleRequest;
use App\Http\Resources\Pos\PosSaleListResource;
use App\Http\Resources\Pos\PosSaleResource;
use App\Models\PosSale;
use App\Services\Pos\DayEndReportService;
use App\Services\Pos\PosSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class PosSaleController extends Controller
{
    public function index(ListPosSalesRequest $request, DayEndReportService $dayEndReportService): JsonResponse
    {
        $this->authorize('viewAny', PosSale::class);

        $validated = $request->validated();
        $limit = min((int) ($validated['limit'] ?? 25), 50);

        $query = PosSale::query()
            ->with(['customer', 'createdBy', 'posSession.cashbox']);

        $dayEndReportService->applySaleFilters($query, $request->user(), $validated);

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }

        if (! empty($validated['document_type'])) {
            $query->where('document_type', (string) $validated['document_type']);
        }

        $sales = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                perPage: $limit,
                columns: ['*'],
                cursorName: 'cursor',
                cursor: $validated['cursor'] ?? null,
            );

        return response()->json([
            'data' => PosSaleListResource::collection(collect($sales->items()))->resolve(),
            'next_cursor' => $sales->nextCursor()?->encode(),
            'prev_cursor' => $sales->previousCursor()?->encode(),
            'limit' => $limit,
        ]);
    }

    public function show(PosSale $posSale): JsonResponse
    {
        $this->authorize('view', $posSale);

        $posSale->loadMissing([
            'customer',
            'createdBy',
            'posSession.cashbox',
            'items.product.brand',
            'payments',
        ]);

        return response()->json([
            'data' => new PosSaleResource($posSale),
        ]);
    }

    public function store(CreatePosSaleRequest $request, PosSaleService $posSaleService): JsonResponse
    {
        $this->authorize('create', PosSale::class);

        $sale = $posSaleService->create($request->user(), $request->validated());

        return response()->json([
            'data' => new PosSaleResource($sale),
        ], HttpStatus::HTTP_CREATED);
    }

    public function cancel(
        CancelPosSaleRequest $request,
        PosSale $posSale,
        PosSaleService $posSaleService
    ): JsonResponse {
        $this->authorize('cancel', $posSale);

        $sale = $posSaleService->cancel(
            user: $request->user(),
            posSale: $posSale,
            note: $request->validated('note')
        );

        return response()->json([
            'data' => new PosSaleResource($sale),
        ]);
    }

    public function update(
        UpdatePosSaleRequest $request,
        PosSale $posSale,
        PosSaleService $posSaleService
    ): JsonResponse {
        $this->authorize('update', $posSale);

        $sale = $posSaleService->updateDocument(
            user: $request->user(),
            posSale: $posSale,
            payload: $request->validated()
        );

        return response()->json([
            'data' => new PosSaleResource($sale),
        ]);
    }

    public function destroy(Request $request, PosSale $posSale, PosSaleService $posSaleService): JsonResponse
    {
        $this->authorize('delete', $posSale);

        $posSaleService->deleteDocument($request->user(), $posSale);

        return response()->json([
            'message' => 'İrsaliye belgesi silindi.',
        ]);
    }

    public function print(PosSale $posSale): LaravelResponse
    {
        $this->authorize('view', $posSale);

        $posSale->loadMissing([
            'customer',
            'createdBy',
            'posSession.cashbox',
            'items.product.brand',
            'payments',
        ]);

        return response()->view('pos.prints.receipt', [
            'sale' => $posSale,
        ], HttpStatus::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function printReceipt(PosSale $posSale): LaravelResponse
    {
        // Backward-compatible alias for older clients.
        return $this->print($posSale);
    }
}
