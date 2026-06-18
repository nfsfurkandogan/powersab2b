<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ListPosExpensesRequest;
use App\Http\Requests\Pos\StorePosExpenseRequest;
use App\Http\Resources\Pos\PosExpenseResource;
use App\Models\PosExpense;
use App\Services\Pos\PosExpenseService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class PosExpenseController extends Controller
{
    public function index(ListPosExpensesRequest $request, PosExpenseService $posExpenseService): JsonResponse
    {
        $this->authorize('viewAny', PosExpense::class);

        $validated = $request->validated();
        $limit = min((int) ($validated['limit'] ?? 10), 50);

        $query = PosExpense::query()->with(['posSession.cashbox', 'createdBy']);
        $posExpenseService->applyFilters($query, $request->user(), $validated);

        $expenses = $query
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => PosExpenseResource::collection($expenses)->resolve(),
            'limit' => $limit,
        ]);
    }

    public function store(StorePosExpenseRequest $request, PosExpenseService $posExpenseService): JsonResponse
    {
        $this->authorize('create', PosExpense::class);

        $expense = $posExpenseService->create($request->user(), $request->validated());

        return response()->json([
            'data' => new PosExpenseResource($expense),
        ], HttpStatus::HTTP_CREATED);
    }
}
