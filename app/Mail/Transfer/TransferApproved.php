<?php

namespace App\Mail\Transfer;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransferApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $transferType,
        public string $fromDepartment,
        public string $toDepartment,
        public ?string $remarks = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تمت الموافقة على طلب النقل',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.transfer.approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
