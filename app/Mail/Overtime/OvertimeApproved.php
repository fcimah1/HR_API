<?php

namespace App\Mail\Overtime;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OvertimeApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $requestDate,
        public string $totalHours,
        public ?string $remarks = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تمت الموافقة على طلب العمل الإضافي',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.overtime.approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
