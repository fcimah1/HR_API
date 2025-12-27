<?php

namespace App\Mail\Transfer;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransferRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $transferType,
        public string $fromDepartment,
        public string $toDepartment,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تم رفض طلب النقل',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.transfer.rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
