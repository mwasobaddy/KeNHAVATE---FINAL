<?php

namespace App\Mail;

use App\Models\AppealMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppealSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public AppealMessage $appeal,
        public User $appealingUser
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $type = $this->appeal->appeal_type === 'ban' ? 'Account Ban' : 'Account Suspension';
        
        return new Envelope(
            subject: "New {$type} Appeal Submitted - {$this->appealingUser->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appeal-submitted',
            with: [
                'appeal' => $this->appeal,
                'user' => $this->appealingUser,
                'appealType' => $this->appeal->appeal_type === 'ban' ? 'ban' : 'suspension',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
