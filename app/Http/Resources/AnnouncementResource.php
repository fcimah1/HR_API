<?php

namespace App\Http\Resources;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->announcement_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'targeting' => [
                'departments' => $this->resolveDepartmentNames($this->department_id, (int) $this->company_id),
                'employees' => $this->resolveEmployeeNames($this->audience_id, (int) $this->company_id),
            ],
            'publisher' => [
                'id' => $this->published_by ? (int) $this->published_by : null,
                'name' => $this->publisher ? $this->publisher->first_name . ' ' . $this->publisher->last_name : null,
            ],
            'is_active' => (bool) $this->is_active,
        ];
    }

    /**
     * Resolve department names from comma-separated IDs within a specific company
     */
    private function resolveDepartmentNames(?string $ids, int $companyId): array
    {
        if (!$ids) {
            return [];
        }

        $idArray = explode(',', $ids);
        $results = [];

        if (in_array('0', $idArray) || in_array('all', $idArray)) {
            $results[] = ['name' => 'الكل'];
        }

        $realIds = array_filter($idArray, fn($id) => is_numeric($id) && $id > 0);
        if (!empty($realIds)) {
            $departments = Department::whereIn('department_id', $realIds)
                ->where('company_id', $companyId)
                ->get(['department_id', 'department_name']);

            foreach ($departments as $dept) {
                $results[] = [
                    'id' => $dept->department_id,
                    'name' => $dept->department_name
                ];
            }
        }

        return $results;
    }

    /**
     * Resolve employee names from comma-separated IDs within a specific company
     */
    private function resolveEmployeeNames(?string $ids, int $companyId): array
    {
        if (!$ids) {
            return [];
        }

        $idArray = explode(',', $ids);
        $results = [];

        if (in_array('0', $idArray) || in_array('all', $idArray)) {
            $results[] = ['name' => 'الكل'];
        }

        $realIds = array_filter($idArray, fn($id) => is_numeric($id) && $id > 0);
        if (!empty($realIds)) {
            $users = User::whereIn('user_id', $realIds)
                ->where('company_id', $companyId)
                ->get(['user_id', 'first_name', 'last_name']);

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->user_id,
                    'name' => trim($user->first_name . ' ' . $user->last_name)
                ];
            }
        }

        return $results;
    }
}
