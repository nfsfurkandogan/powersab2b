<?php

namespace App\Http\Middleware;

use App\Support\QueryMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiPerformanceLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldLog()) {
            return $next($request);
        }

        /** @var QueryMetrics $metrics */
        $metrics = app(QueryMetrics::class);
        $metrics->reset();
        $startedAt = hrtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);

        Log::info('API request performance', [
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'db_query_count' => $metrics->count(),
            'db_total_ms' => round($metrics->totalMs(), 2),
            'user_id' => $request->user()?->id,
        ]);

        return $response;
    }

    private function shouldLog(): bool
    {
        return app()->isLocal() || (bool) env('PERF_ENDPOINT_LOG', false);
    }
}
