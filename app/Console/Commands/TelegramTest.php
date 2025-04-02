<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use App\Jobs\SendReportNotification;

class TelegramTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:test {reportId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $reportId = $this->argument('reportId');
        $report = Report::findOrFail($reportId);

        SendReportNotification::dispatch($report);
    }
}
