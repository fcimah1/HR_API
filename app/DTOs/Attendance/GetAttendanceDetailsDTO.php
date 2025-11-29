<?php

namespace App\DTOs\Attendance;

use Illuminate\Http\Request;
use Carbon\Carbon;

class GetAttendanceDetailsDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $date,
    ) {}

    /**
     * Create DTO from request
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            userId: (int) $request->input('user_id'),
            date: Carbon::parse($request->input('date'))->format('Y-m-d'),
        );
    }

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'date' => $this->date,
        ];
    }
}
