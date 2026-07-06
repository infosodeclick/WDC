<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PortalNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function build(): self
    {
        return $this
            ->subject($this->notification->title)
            ->view('emails.portal-notification');
    }
}
