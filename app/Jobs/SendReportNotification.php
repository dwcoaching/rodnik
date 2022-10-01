<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\ReportNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\ReportMapNotification;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ReportPhotoNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendReportNotification implements ShouldQueue
{
    protected $report;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Report $report)
    {
        //
        $this->report = $report;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $mapNotification = new ReportMapNotification($this->report);

        Notification::route('telegram', config('services.telegram-bot-api.channel_id'))
            ->notify($mapNotification);
        $notification = new ReportNotification($this->report);

        Notification::route('telegram', config('services.telegram-bot-api.channel_id'))
            ->notify($notification);


        // $photoCount = $this->report->photos->count();

        // if ($photoCount) {
        //     $photoNotification = new ReportPhotoNotification($this->report->photos->first(), $photoCount);

        //     Notification::route('telegram', config('services.telegram-bot-api.channel_id'))
        //         ->notify($photoNotification);
        // }
    }
}
