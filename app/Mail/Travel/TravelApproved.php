<?php

namespace App\Mail\Travel;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TravelApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $destination,
        public string $startDate,
        public string $endDate,
        public ?string $remarks = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تمت الموافقة على طلب السفر',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.travel.travel-approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
