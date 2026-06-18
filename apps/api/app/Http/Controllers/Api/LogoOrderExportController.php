<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoOrdersRequest;
use App\Http\Requests\Integration\ListPendingLogoOrdersRequest;
use App\Services\Integrations\Logo\LogoOrderExportService;
use Illuminate\Http\JsonResponse;

class LogoOrderExportController extends Controller
{
    public function index(
        ListPendingLogoOrdersRequest $request,
        LogoOrderExportService $service
    ): JsonResponse {
        return response()->json($service->pending($request->validated()));
    }

    public function acknowledge(
        AcknowledgeLogoOrdersRequest $request,
        LogoOrderExportService $service
    ): JsonResponse {
        $summary = $service->acknowledge($request->validated());

        return response()->json([
            'message' => 'Logo order acknowledgements processed.',
            'summary' => $summary,
        ]);
    }
}
