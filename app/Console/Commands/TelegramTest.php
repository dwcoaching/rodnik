<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use App\Jobs\SendReportNotification;
use App\Notifications\ReportNotification;
use Illuminate\Support\Facades\Notification;

class TelegramTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:test';

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
        SendReportNotification::dispatch(Report::find(35));
    }
}
