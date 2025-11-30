<?php

namespace App\Mail\LeaveAdjustment;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdjustmentSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $leaveType,
        public float $adjustHours,
        public string $reason
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'طلب تسوية إجازة جديد',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave_adjustment.adjustment-submitted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
