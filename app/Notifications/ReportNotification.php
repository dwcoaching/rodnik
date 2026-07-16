<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramFile;
use NotificationChannels\Telegram\TelegramMessage;

final class ReportNotification extends Notification
{
    use Queueable;

    protected $report;

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

        foreach ([$this->report->state, $this->report->quality, $this->report->access] as $condition) {
            if ($condition !== null) {
                $tags[] = mb_strtolower($condition->getLabel());
            }
        }
        if ($this->report->littered) {
            $tags[] = 'littered';
        }
        if ($this->report->ruined) {
            $tags[] = 'ruined';
        }

        if (count($tags)) {
            $tags[0] = mb_ucfirst($tags[0]);
        }

        if (! $photoCount) {
            return TelegramMessage::create()
                ->options(
                    [
                        'parse_mode' => 'HTML',
                    ]
                )
                ->view('telegram.report', [
                    'report' => $this->report,
                    'photoCount' => 0,
                    'tags' => $tags,
                ]);
        }

        return TelegramFile::create()
            ->file($this->report->photos->first()->fullPath(), 'photo')
            ->options(['parse_mode' => 'HTML'])
            ->view('telegram.report', [
                'report' => $this->report,
                'photoCount' => $photoCount,
                'tags' => $tags,
            ]);
    }
}
