<?php

namespace App\Mail\AdvanceSalary;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdvanceSalaryApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public float $amount,
        public string $salaryType,
        public ?string $remarks = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تمت الموافقة على طلب السلفة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.advance_salary.approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
