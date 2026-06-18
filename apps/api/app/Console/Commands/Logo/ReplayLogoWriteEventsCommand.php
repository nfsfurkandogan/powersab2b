<?php

namespace App\Console\Commands\Logo;

use App\Services\Integrations\Logo\LogoWriteReplayService;
use Illuminate\Console\Command;

class ReplayLogoWriteEventsCommand extends Command
{
    protected $signature = 'logo:writes-replay
        {--domain= : Sadece customers-write veya collections-write}
        {--limit=100 : Maksimum event sayisi}
        {--force : LOGO_WRITE_ENABLED false olsa bile RabbitMQ transportuna publish etmeyi dene}';

    protected $description = 'RabbitMQ transport aciksa queued veya publish_failed Logo write eventlerini tekrar publish eder';

    public function handle(LogoWriteReplayService $service): int
    {
        $summary = $service->replay(
            force: (bool) $this->option('force'),
            domain: $this->option('domain') ? (string) $this->option('domain') : null,
            limit: is_numeric($this->option('limit')) ? (int) $this->option('limit') : 100,
        );

        $this->table(
            ['Scanned', 'Published', 'Failed', 'Skipped'],
            [[
                $summary['scanned'],
                $summary['published'],
                $summary['failed'],
                $summary['skipped'],
            ]]
        );

        if ($summary['published'] > 0) {
            $this->info('Logo write replay tamamlandi.');
        } else {
            $this->warn('Replay tamamlandi ancak publish edilen event yok.');
        }

        return self::SUCCESS;
    }
}
