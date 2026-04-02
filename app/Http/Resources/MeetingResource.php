<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->meeting_id,
            'title' => $this->meeting_title,
            'details' => [
                'date' => $this->meeting_date,
                'time' => $this->meeting_time,
                'room' => $this->meeting_room,
                'color' => $this->meeting_color,
                'note' => $this->meeting_note,
            ],
            'participants' => $this->resolveParticipantNames($this->employee_id, (int)$this->company_id),
        ];
    }

    /**
     * حل أسماء المشاركين من المعرفات
     */
    private function resolveParticipantNames(?string $ids, int $companyId): array
    {
        if (empty($ids) || $ids === '0') {
            return [];
        }

        $idArray = array_filter(explode(',', $ids), fn($id) => is_numeric($id) && $id > 0);

        if (empty($idArray)) {
            return [];
        }

        $users = User::whereIn('user_id', $idArray)
            ->where('company_id', $companyId)
            ->get(['user_id', 'first_name', 'last_name']);

        return $users->map(fn($user) => [
            'id' => $user->user_id,
            'name' => trim($user->first_name . ' ' . $user->last_name),
        ])->toArray();
    }
}
