<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoPosExpensesRequest;
use App\Http\Requests\Integration\ListPendingLogoPosExpensesRequest;
use App\Http\Requests\Integration\SyncLogoPosExpensesRequest;
use App\Services\Integrations\Logo\LogoPosExpenseImportService;
use App\Services\Integrations\Logo\LogoPosExpenseExportService;
use Illuminate\Http\JsonResponse;

class LogoPosExpenseExportController extends Controller
{
    public function sync(
        SyncLogoPosExpensesRequest $request,
        LogoPosExpenseImportService $service
    ): JsonResponse {
        $summary = $service->sync($request->validated());

        return response()->json([
            'message' => 'Logo POS expenses synchronized.',
            'summary' => $summary,
        ]);
    }

    public function index(
        ListPendingLogoPosExpensesRequest $request,
        LogoPosExpenseExportService $service
    ): JsonResponse {
        return response()->json($service->pending($request->validated()));
    }

    public function acknowledge(
        AcknowledgeLogoPosExpensesRequest $request,
        LogoPosExpenseExportService $service
    ): JsonResponse {
        $summary = $service->acknowledge($request->validated());

        return response()->json([
            'message' => 'Logo POS expense acknowledgements processed.',
            'summary' => $summary,
        ]);
    }
}
