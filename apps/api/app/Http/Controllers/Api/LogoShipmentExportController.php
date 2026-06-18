<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoShipmentsRequest;
use App\Http\Requests\Integration\ListPendingLogoShipmentsRequest;
use App\Services\Integrations\Logo\LogoShipmentExportService;
use Illuminate\Http\JsonResponse;

class LogoShipmentExportController extends Controller
{
    public function index(
        ListPendingLogoShipmentsRequest $request,
        LogoShipmentExportService $service
    ): JsonResponse {
        return response()->json($service->pending($request->validated()));
    }

    public function acknowledge(
        AcknowledgeLogoShipmentsRequest $request,
        LogoShipmentExportService $service
    ): JsonResponse {
        $summary = $service->acknowledge($request->validated());

        return response()->json([
            'message' => 'Logo shipment acknowledgements processed.',
            'summary' => $summary,
        ]);
    }
}
