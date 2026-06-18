<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoReturnsRequest;
use App\Http\Requests\Integration\ListPendingLogoReturnsRequest;
use App\Services\Integrations\Logo\LogoReturnExportService;
use Illuminate\Http\JsonResponse;

class LogoReturnExportController extends Controller
{
    public function index(
        ListPendingLogoReturnsRequest $request,
        LogoReturnExportService $service
    ): JsonResponse {
        return response()->json($service->pending($request->validated()));
    }

    public function acknowledge(
        AcknowledgeLogoReturnsRequest $request,
        LogoReturnExportService $service
    ): JsonResponse {
        $summary = $service->acknowledge($request->validated());

        return response()->json([
            'message' => 'Logo return acknowledgements processed.',
            'summary' => $summary,
        ]);
    }
}
