<?php

namespace App\Mail\Complaint;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplaintSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $complaintType,
        public string $complaintSubject,
        public ?string $description = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'شكوى جديدة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.complaint.submitted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
