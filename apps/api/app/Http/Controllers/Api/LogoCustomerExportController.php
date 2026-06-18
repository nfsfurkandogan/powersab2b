<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoCustomersRequest;
use App\Http\Requests\Integration\ListPendingLogoCustomersRequest;
use App\Services\Integrations\Logo\LogoCustomerExportService;
use Illuminate\Http\JsonResponse;

class LogoCustomerExportController extends Controller
{
    public function index(
        ListPendingLogoCustomersRequest $request,
        LogoCustomerExportService $service
    ): JsonResponse {
        $payload = $service->pending($request->validated());

        return response()->json($payload);
    }

    public function acknowledge(
        AcknowledgeLogoCustomersRequest $request,
        LogoCustomerExportService $service
    ): JsonResponse {
        $summary = $service->acknowledge($request->validated());

        return response()->json([
            'message' => 'Logo customer acknowledgements processed.',
            'summary' => $summary,
        ]);
    }
}
