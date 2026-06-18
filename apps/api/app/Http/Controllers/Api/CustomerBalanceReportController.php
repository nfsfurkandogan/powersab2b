<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\DispatchesAsyncReports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\CustomerBalanceReportRequest;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class CustomerBalanceReportController extends Controller
{
    use DispatchesAsyncReports;

    public function __invoke(CustomerBalanceReportRequest $request, ReportService $reports): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if ($request->boolean('async')) {
            return $this->queueReportRun(
                user: $user,
                reportKey: ReportService::KEY_CUSTOMER_BALANCES,
                parameters: Arr::except($validated, ['async'])
            );
        }

        return response()->json($reports->customerBalances(
            filters: Arr::except($validated, ['async']),
            user: $user
        ));
    }
}
