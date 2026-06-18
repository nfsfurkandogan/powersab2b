<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\AcknowledgeLogoCollectionsRequest;
use App\Http\Requests\Integration\ListPendingLogoCollectionsRequest;
use App\Services\Integrations\Logo\LogoCollectionExportService;
use Illuminate\Http\JsonResponse;

class LogoCollectionExportController extends Controller
{
    public function index(
        ListPendingLogoCollectionsRequest $request,
        LogoCollectionExportService $service
    ): JsonResponse {
        $payload = $service->pending($request->validated());

        return response()->json($payload);
    }

    public function acknowledge(
        AcknowledgeLogoCollectionsRequest $request,
        LogoCollectionExportService $service
    ): JsonResponse {
        $summary = $service->acknowledge($request->validated());

        return response()->json([
            'message' => 'Logo collection acknowledgements processed.',
            'summary' => $summary,
        ]);
    }
}
