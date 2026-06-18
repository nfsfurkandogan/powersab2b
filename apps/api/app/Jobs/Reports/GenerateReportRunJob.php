<?php

namespace App\Jobs\Reports;

use App\Models\ReportRun;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateReportRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public int $reportRunId)
    {
        $this->onQueue((string) config('reports.queue', 'default'));
    }

    public function handle(ReportService $reports): void
    {
        $run = ReportRun::query()->find($this->reportRunId);
        if ($run === null || $run->status !== 'queued') {
            return;
        }

        $run->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        /** @var User|null $user */
        $user = User::query()->find($run->user_id);
        if ($user === null) {
            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => 'User context not found.',
            ])->save();

            return;
        }

        try {
            $payload = $reports->generate(
                reportKey: (string) $run->report_key,
                filters: is_array($run->parameters) ? $run->parameters : [],
                user: $user
            );

            $run->forceFill([
                'status' => 'completed',
                'result' => $payload,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            $run->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
        }
    }
}
