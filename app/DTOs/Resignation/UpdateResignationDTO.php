<?php

namespace App\DTOs\Resignation;

use Spatie\LaravelData\Data;

class UpdateResignationDTO extends Data
{
    public function __construct(
        public readonly ?string $noticeDate = null,
        public readonly ?string $resignationDate = null,
        public readonly ?string $reason = null,
        public readonly ?array $notifySendTo = [],
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            noticeDate: $data['notice_date'] ?? null,
            resignationDate: $data['resignation_date'] ?? null,
            reason: $data['reason'] ?? null,
            notifySendTo: $data['notify_send_to'] ?? [],
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->noticeDate !== null) {
            $data['notice_date'] = $this->noticeDate;
        }

        if ($this->resignationDate !== null) {
            $data['resignation_date'] = $this->resignationDate;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;
        }

        if ($this->notifySendTo !== null) {
            // $data['notify_send_to'] = !empty($this->notifySendTo) ? json_encode($this->notifySendTo) : null;
            $data['notify_send_to'] = is_array($this->notifySendTo) ? implode(',', $this->notifySendTo) : $this->notifySendTo;

        }

        return $data;
    }
}
