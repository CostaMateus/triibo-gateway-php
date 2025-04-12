<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ScheduleFailure extends Notification
{
    use Queueable;
    private $blade;
    private $details;

    /**
     * Create a new notification instance.
     *
     * @param mixed $blade
     * @param mixed $details
     *
     * @return void
     */
    public function __construct( $blade, $details )
    {
        $this->blade = $blade;
        $this->details = $details;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function via( $notifiable )
    {
        return ["mail"];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return MailMessage
     */
    public function toMail( $notifiable )
    {
        return ( new MailMessage() )->subject( $this->details[ "subject" ] )
            ->markdown(
                "mail.{$this->blade}.schedule-failure", ["title" => $this->details[ "title" ],
                    "method" => $this->details[ "method" ],
                    "catch" => $this->details[ "catch" ],
                    "stacktrace" => $this->details[ "stacktrace" ], ] );
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function toArray( $notifiable )
    {
        return [];
    }
}
