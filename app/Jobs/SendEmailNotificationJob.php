<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Mailable $mailable,
        public string $recipient
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('SendEmailNotificationJob started', [
                'recipient' => $this->recipient,
                'mailable' => get_class($this->mailable),
            ]);

            Mail::to($this->recipient)->send($this->mailable);

            Log::info('SendEmailNotificationJob completed', [
                'recipient' => $this->recipient,
            ]);
        } catch (\Exception $e) {
            Log::error('SendEmailNotificationJob failed', [
                'recipient' => $this->recipient,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
