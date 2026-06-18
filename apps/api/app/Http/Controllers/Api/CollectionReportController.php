<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\DispatchesAsyncReports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\CollectionReportRequest;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class CollectionReportController extends Controller
{
    use DispatchesAsyncReports;

    public function __invoke(CollectionReportRequest $request, ReportService $reports): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($request->boolean('async')) {
            return $this->queueReportRun(
                user: $user,
                reportKey: ReportService::KEY_COLLECTIONS,
                parameters: Arr::except($validated, ['async'])
            );
        }

        return response()->json($reports->collections(
            filters: Arr::except($validated, ['async']),
            user: $user
        ));
    }
}
