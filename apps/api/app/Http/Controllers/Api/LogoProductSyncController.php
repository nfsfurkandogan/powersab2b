<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\SyncLogoProductsRequest;
use App\Services\Integrations\Logo\LogoProductSyncService;
use Illuminate\Http\JsonResponse;

class LogoProductSyncController extends Controller
{
    public function store(
        SyncLogoProductsRequest $request,
        LogoProductSyncService $service
    ): JsonResponse {
        $summary = $service->sync($request->validated());

        return response()->json([
            'message' => 'Logo products synchronized.',
            'summary' => $summary,
        ]);
    }
}
