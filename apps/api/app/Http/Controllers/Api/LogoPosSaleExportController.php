<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoPosSalesRequest;
use App\Http\Requests\Integration\ListPendingLogoPosSalesRequest;
use App\Services\Integrations\Logo\LogoPosSaleExportService;
use Illuminate\Http\JsonResponse;

class LogoPosSaleExportController extends Controller
{
    public function index(
        ListPendingLogoPosSalesRequest $request,
        LogoPosSaleExportService $service
    ): JsonResponse {
        return response()->json($service->pending($request->validated()));
    }

    public function acknowledge(
        AcknowledgeLogoPosSalesRequest $request,
        LogoPosSaleExportService $service
    ): JsonResponse {
        $summary = $service->acknowledge($request->validated());

        return response()->json([
            'message' => 'Logo POS sale acknowledgements processed.',
            'summary' => $summary,
        ]);
    }
}
