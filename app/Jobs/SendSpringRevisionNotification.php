<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use App\Models\SpringRevision;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\ReportNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\ReportMapNotification;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ReportPhotoNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Notifications\SpringRevisionNotification;

class SendSpringRevisionNotification implements ShouldQueue
{
    protected $revision;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(SpringRevision $revision)
    {
        //
        $this->revision = $revision;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $notification = new SpringRevisionNotification($this->revision);

        Notification::route('telegram', config('services.telegram-bot-api.channel_id'))
            ->notify($notification);
    }
}
