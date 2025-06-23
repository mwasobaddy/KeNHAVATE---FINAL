<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * KeNHAVATE Innovation Portal - OTP Email
 * Sends verification codes for secure authentication
 */
class OTPMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $purpose;
    public int $expiresIn;
    public string $userEmail;
    public string $first_name;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->otpCode = $data['otp_code'];
        $this->purpose = $data['purpose'];
        $this->expiresIn = $data['expires_in'];
        $this->userEmail = $data['email'];
        $this->first_name = $data['first_name'] ?? 'User'; // Default to 'User' if not provided
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->purpose) {
            'login' => 'Your KeNHAVATE Login Verification Code',
            'registration' => 'Welcome to KeNHAVATE - Verify Your Email',
            'password_reset' => 'KeNHAVATE Password Reset Code',
            default => 'Your KeNHAVATE Verification Code'
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otpCode' => $this->otpCode,
                'purpose' => $this->purpose,
                'expiresIn' => $this->expiresIn,
                'userEmail' => $this->userEmail,
                'first_name' => $this->first_name,
            ]
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
