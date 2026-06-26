<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoPurchaseReceiptsRequest;
use App\Http\Requests\Integration\ListPendingLogoPurchaseReceiptsRequest;
use App\Services\Integrations\Logo\LogoPurchaseReceiptExportService;
use Illuminate\Http\JsonResponse;

class LogoPurchaseReceiptExportController extends Controller
{
    public function index(
        ListPendingLogoPurchaseReceiptsRequest $request,
        LogoPurchaseReceiptExportService $service
    ): JsonResponse {
        return response()->json($service->pending($request->validated()));
    }

    public function acknowledge(
        AcknowledgeLogoPurchaseReceiptsRequest $request,
        LogoPurchaseReceiptExportService $service
    ): JsonResponse {
        return response()->json([
            'message' => 'Logo purchase receipt acknowledgements processed.',
            'summary' => $service->acknowledge($request->validated()),
        ]);
    }
}
