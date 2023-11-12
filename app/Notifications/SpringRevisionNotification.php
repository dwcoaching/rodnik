<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use App\Models\SpringRevision;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\Telegram\TelegramFile;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class SpringRevisionNotification extends Notification
{
    protected $revision;

    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SpringRevision $revision)
    {
        $this->revision = $revision;
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
        return TelegramMessage::create()
            ->options(
                [
                    'parse_mode' => 'HTML'
                ]
            )
            ->view('telegram.spring-revision', [
                'revision' => $this->revision,
            ]);
    }
}
