<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A short message from one staff member to another (plain text). */
class StaffMessage extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $senderName,
        public readonly string $topic,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->topic);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.staff-message');
    }
}
