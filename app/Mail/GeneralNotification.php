<?php

namespace App\Mail;

use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GeneralNotification extends Mailable
{
    use Queueable, SerializesModels;

    public AppNotification $notification;
    public array $data;

    /**
     * Create a new message instance.
     */
    public function __construct(AppNotification $notification, array $data = [])
    {
        $this->notification = $notification;
        $this->data = $data;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject($this->notification->title)
                    ->view('emails.general-notification')
                    ->with([
                        'notification' => $this->notification,
                        'data' => $this->data
                    ]);
    }
}
