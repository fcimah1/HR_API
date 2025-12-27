<?php

namespace App\Mail\Resignation;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResignationApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $resignationDate,
        public string $lastWorkingDay,
        public ?string $remarks = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تمت الموافقة على طلب الاستقالة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resignation.approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
