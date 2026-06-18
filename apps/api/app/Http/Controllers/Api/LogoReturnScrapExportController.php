<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoReturnsRequest;
use App\Http\Requests\Integration\ListPendingLogoReturnsRequest;
use App\Services\Integrations\Logo\LogoReturnScrapExportService;
use Illuminate\Http\JsonResponse;

class LogoReturnScrapExportController extends Controller
{
    public function index(
        ListPendingLogoReturnsRequest $request,
        LogoReturnScrapExportService $service
    ): JsonResponse {
        return response()->json($service->pending($request->validated()));
    }

    public function acknowledge(
        AcknowledgeLogoReturnsRequest $request,
        LogoReturnScrapExportService $service
    ): JsonResponse {
        return response()->json([
            'message' => 'Logo return scrap acknowledgements processed.',
            'summary' => $service->acknowledge($request->validated()),
        ]);
    }
}
