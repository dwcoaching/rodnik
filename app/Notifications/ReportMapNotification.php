<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramLocation;

class ReportMapNotification extends Notification
{
    protected $report;

    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($report)
    {
        //
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

    public function toTelegram()
    {
        // telegram photo
        // generate photo
        // or start with location

        // return TelegramMessage::create()
        //     ->view('telegram.report', [
        //         'report' => $this->report
        //     ]);

        return TelegramLocation::create()
            ->latitude($this->report->spring->latitude)
            ->longitude($this->report->spring->longitude);
    }
}
