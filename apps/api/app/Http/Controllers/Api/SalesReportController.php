<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\DispatchesAsyncReports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\SalesReportRequest;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SalesReportController extends Controller
{
    use DispatchesAsyncReports;

    public function __invoke(SalesReportRequest $request, ReportService $reports): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($request->boolean('async')) {
            return $this->queueReportRun(
                user: $user,
                reportKey: ReportService::KEY_SALES,
                parameters: Arr::except($validated, ['async'])
            );
        }

        return response()->json($reports->sales(
            filters: Arr::except($validated, ['async']),
            user: $user
        ));
    }
}
