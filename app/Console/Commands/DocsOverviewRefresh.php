<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Docs\DocsOverview;
use Illuminate\Console\Command;

final class DocsOverviewRefresh extends Command
{
    protected $signature = 'docs:overview-refresh';

    protected $description = 'Recompute the cached contributor and location aggregates of the docs overview';

    public function handle(DocsOverview $overview): int
    {
        $overview->refresh();
        $this->info('Docs overview refreshed.');

        return self::SUCCESS;
    }
}
