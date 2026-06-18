<?php

namespace App\Console\Commands\Logo;

use App\Services\Integrations\Logo\LogoLedgerSyncService;
use Illuminate\Console\Command;

class BackfillLogoCollectionsCommand extends Command
{
    protected $signature = 'logo:collections-backfill {--customer-id= : Sadece belirli customer_id icin calistir}';

    protected $description = 'Backfill Logo payment ledger entries into B2B collections';

    public function handle(LogoLedgerSyncService $service): int
    {
        $customerIdOption = $this->option('customer-id');
        $customerId = is_numeric($customerIdOption) ? (int) $customerIdOption : null;

        $summary = $service->backfillCollections($customerId);

        $this->table(
            ['Scanned', 'Created', 'Updated', 'Skipped'],
            [[
                $summary['scanned'],
                $summary['created'],
                $summary['updated'],
                $summary['skipped'],
            ]]
        );

        $this->info('Logo collection backfill tamamlandi.');

        return self::SUCCESS;
    }
}
