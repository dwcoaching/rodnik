<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\Telegram\TelegramFile;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class ReportNotification extends Notification
{
    protected $report;

    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function toTelegram()
    {
        $photoCount = $this->report->photos->count();

        $tags = [];

        if ($this->report->state == 'notfound') {$tags[] = 'water source not found';}
        if ($this->report->state == 'running') {$tags[] = 'watered';}
        if ($this->report->state == 'dry') {$tags[] = 'dry';}
        if ($this->report->quality == 'good') {$tags[] = 'good water';}
        if ($this->report->quality == 'bad') {$tags[] = 'poor water';}

        if (count($tags)) {
            $tags[0] = mb_ucfirst($tags[0]);
        }

        if (! $photoCount) {
            return TelegramMessage::create()
                ->options(
                    [
                        'parse_mode' => 'HTML'
                    ]
                )
                ->view('telegram.report', [
                    'report' => $this->report,
                    'photoCount' => 0,
                    'tags' => $tags
                ]);
        }

        return TelegramFile::create()
            ->file($this->report->photos->first()->fullPath(), 'photo')
            ->options(['parse_mode' => 'HTML'])
            ->view('telegram.report', [
                'report' => $this->report,
                'photoCount' => $photoCount,
                'tags' => $tags
            ]);
    }
}
