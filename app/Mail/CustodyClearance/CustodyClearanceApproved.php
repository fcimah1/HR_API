<?php

namespace App\Mail\CustodyClearance;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustodyClearanceApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $employeeName,
        public readonly string $clearanceDate,
        public readonly string $clearanceType,
        public readonly ?string $remarks = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تمت الموافقة على طلب إخلاء الطرف',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.custody-clearance.approved',
            with: [
                'employeeName' => $this->employeeName,
                'clearanceDate' => $this->clearanceDate,
                'clearanceType' => $this->getClearanceTypeText(),
                'remarks' => $this->remarks,
            ],
        );
    }

    protected function getClearanceTypeText(): string
    {
        return match ($this->clearanceType) {
            'resignation' => 'استقالة',
            'termination' => 'إنهاء خدمة',
            'transfer' => 'نقل',
            default => 'أخرى',
        };
    }
}
