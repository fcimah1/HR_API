<?php

namespace App\Mail\CustodyClearance;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustodyClearanceSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $employeeName,
        public readonly string $clearanceDate,
        public readonly string $clearanceType,
        public readonly int $assetCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'طلب إخلاء طرف عهد جديد - ' . $this->employeeName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.custody-clearance.submitted',
            with: [
                'employeeName' => $this->employeeName,
                'clearanceDate' => $this->clearanceDate,
                'clearanceType' => $this->getClearanceTypeText(),
                'assetCount' => $this->assetCount,
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
