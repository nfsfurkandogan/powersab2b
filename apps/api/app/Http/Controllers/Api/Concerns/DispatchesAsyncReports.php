<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Jobs\Reports\GenerateReportRunJob;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Http\JsonResponse;

trait DispatchesAsyncReports
{
    protected function queueReportRun(User $user, string $reportKey, array $parameters): JsonResponse
    {
        $run = ReportRun::query()->create([
            'user_id' => $user->id,
            'report_key' => $reportKey,
            'status' => 'queued',
            'parameters' => $parameters,
        ]);

        GenerateReportRunJob::dispatch($run->id);

        return response()->json([
            'message' => 'Report request queued.',
            'run' => [
                'id' => $run->id,
                'report_key' => $run->report_key,
                'status' => $run->status,
                'created_at' => $run->created_at,
            ],
        ], 202);
    }
}
