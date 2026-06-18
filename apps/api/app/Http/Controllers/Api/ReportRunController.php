<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportRunController extends Controller
{
    public function show(Request $request, ReportRun $reportRun): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin') && $reportRun->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to access this report run.');
        }

        return response()->json([
            'run' => [
                'id' => $reportRun->id,
                'report_key' => $reportRun->report_key,
                'status' => $reportRun->status,
                'parameters' => $reportRun->parameters,
                'result' => $reportRun->result,
                'error_message' => $reportRun->error_message,
                'started_at' => $reportRun->started_at,
                'completed_at' => $reportRun->completed_at,
                'created_at' => $reportRun->created_at,
                'updated_at' => $reportRun->updated_at,
            ],
        ]);
    }
}
