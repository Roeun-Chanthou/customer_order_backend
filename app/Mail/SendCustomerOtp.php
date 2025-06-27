<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendCustomerOtp extends Mailable
{
    // use Queueable, SerializesModels;

    // public $otp;

    // public function __construct($otp)
    // {
    //     $this->otp = $otp;
    // }

    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Your OTP Code',
    //     );
    // }

    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'emails.customer_otp',
    //         with: ['otp' => $this->otp],
    //     );
    // }

    // public function attachments(): array
    // {
    //     return [];
    // }

    use Queueable, SerializesModels;

    public $otp;

    /**
     * Create a new message instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your OTP Code')
                    ->text('emails.customer-otp-text')
                    ->with([
                        'otp' => $this->otp,
                    ]);
    }
}
