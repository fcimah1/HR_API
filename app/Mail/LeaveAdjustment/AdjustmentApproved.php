<?php

namespace App\Mail\LeaveAdjustment;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdjustmentApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $leaveType,
        public float $adjustHours,
        public ?string $remarks = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تمت الموافقة على تسوية الإجازة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave_adjustment.adjustment-approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
