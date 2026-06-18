<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\SyncLogoLedgerEntriesRequest;
use App\Services\Integrations\Logo\LogoLedgerSyncService;
use Illuminate\Http\JsonResponse;

class LogoLedgerSyncController extends Controller
{
    public function store(
        SyncLogoLedgerEntriesRequest $request,
        LogoLedgerSyncService $service
    ): JsonResponse {
        $summary = $service->sync($request->validated());

        return response()->json([
            'message' => 'Logo ledger entries synchronized.',
            'summary' => $summary,
        ]);
    }
}
