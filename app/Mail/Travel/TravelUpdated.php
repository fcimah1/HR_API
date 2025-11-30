<?php

namespace App\Mail\Travel;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TravelUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $destination,
        public string $startDate,
        public string $endDate,
        public string $purpose
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم تحديث طلب السفر',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.travel.travel-updated',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
