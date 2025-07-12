<?php

namespace App\Notifications;

use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\Telegram\TelegramFile;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Telegram\TelegramChannel;

class ReportPhotoNotification extends Notification
{
    protected $photo;
    protected $photoCount;

    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Photo $photo, $photoCount)
    {
        $this->photo = $photo;
        $this->photoCount = $photoCount;
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
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
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
        if ($this->photoCount < 2) {
            $content = duo_route(['spring' => $this->photo->report->spring->id]);
        } else {
            $content = decline_number($this->photoCount - 1, [' more photo', 'more photos', 'more photos']) . ' at ' . duo_route(['spring' => $this->photo->report->spring->id]);
        }

        return TelegramFile::create()
            ->file($this->photo->fullPath(), 'photo')
            ->options(
                [
                    'parse_mode' => 'HTML'
                ]
            )
            ->view('telegram.report', [
                'report' => $this->photo->report
            ]);
            //->content($content);
    }
}
