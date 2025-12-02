<?php

namespace App\Mail\AdvanceSalary;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdvanceSalarySubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public float $amount,
        public string $salaryType
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'طلب سلفة جديد',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.advance_salary.submitted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
