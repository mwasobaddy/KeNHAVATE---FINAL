<?php

namespace App\Mail;

use App\Models\Collaboration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CollaborationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $collaboration;
    public $inviter;
    public $idea;

    public function __construct(Collaboration $collaboration)
    {
        $this->collaboration = $collaboration;
        $this->inviter = $collaboration->inviter;
        $this->idea = $collaboration->collaborable;
    }

    public function build()
    {
        return $this->subject('You have been invited to collaborate on an idea')
            ->view('emails.collaboration-invitation');
    }
}
