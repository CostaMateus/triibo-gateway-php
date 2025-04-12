<?php

namespace App\Notifications\FastShop;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OrderError extends Notification
{
    use Queueable;
    private $data;

    /**
     * Create a new notification instance.
     *
     * @param mixed $data
     *
     * @return void
     */
    public function __construct( $data )
    {
        $this->data = $data;
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
        $mail = new MailMessage();

        return $mail->subject( $this->data[ "subject" ] )
            ->markdown(
                "mail.marketplace.fastshop.order_error", ["title" => $this->data[ "title" ],
                    "data" => $this->data[ "data" ], ] );
    }
}
