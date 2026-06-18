<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\SyncLogoCustomersRequest;
use App\Services\Integrations\Logo\LogoCustomerSyncService;

class LogoCustomerSyncController extends Controller
{
    public function store(
        SyncLogoCustomersRequest $request,
        LogoCustomerSyncService $service
    ) {
        $summary = $service->sync($request->validated());

        return response()->json([
            'message' => 'Logo customers synchronized.',
            'summary' => $summary,
        ]);
    }
}
