<?php

declare(strict_types = 1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

final class WebhookMarketplace extends Notification
{
    use Queueable;
    private $details;

    /**
     * Create a new notification instance.
     *
     * @param mixed $details
     *
     * @return void
     */
    public function __construct( $details )
    {
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
        return [ "mail" ];
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
                "mail.marketplace.webhook", [
                    "title" => $this->details[ "title" ],
                    "class" => $this->details[ "class" ],
                    "params" => $this->details[ "params" ],
                    "stacktrace" => $this->details[ "stacktrace" ]
                ] );
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
