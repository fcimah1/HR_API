<?php

namespace App\Mail\Resignation;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResignationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $resignationDate,
        public string $lastWorkingDay,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم رفض طلب الاستقالة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resignation.rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
