<?php

namespace App\Mail\LeaveAdjustment;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdjustmentRejected extends Mailable
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
            subject: 'تم رفض تسوية الإجازة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave_adjustment.adjustment-rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
