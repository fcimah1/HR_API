<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // تحويل complaint_against من string إلى array من الموظفين
        $complainAgainstIds = $this->getEmployeeIdsArray($this->complaint_against);
        $complainAgainstEmployees = $this->getEmployeesInfo($complainAgainstIds);

        return [
            'complaint_id' => $this->complaint_id,
            'company_id' => $this->company_id,
            'complaint_from' => $this->complaint_from,
            'title' => $this->title,
            'complaint_date' => $this->complaint_date,
            'complaint_against' => $this->complaint_against,
            'complaint_against_employees' => $complainAgainstEmployees,
            'description' => $this->description,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'status_text_en' => $this->status_text_en,
            'created_at' => $this->created_at,

            // معلومات الموظف المقدم للشكوى
            'employee_name' => $this->when(
                $this->relationLoaded('employee'),
                fn() => $this->employee ? ($this->employee->first_name . ' ' . $this->employee->last_name) : 'غير محدد'
            ),

            // معلومات الموظف إذا كانت محملة
            'employee' => $this->when($this->relationLoaded('employee'), function () {
                if (!$this->employee) return null;

                $firstName = $this->employee->first_name ?? '';
                $lastName = $this->employee->last_name ?? '';
                $fullName = trim($firstName . ' ' . $lastName);

                return [
                    'user_id' => $this->employee->user_id,
                    'first_name' => $firstName ?: null,
                    'last_name' => $lastName ?: null,
                    'email' => $this->employee->email,
                    'full_name' => $fullName ?: 'غير محدد',
                ];
            }),

            // معلومات من أضاف الطلب
            'added_by_name' => $this->when(
                $this->relationLoaded('addedBy'),
                fn() => $this->addedBy ? ($this->addedBy->first_name . ' ' . $this->addedBy->last_name) : 'غير محدد'
            ),
        ];
    }

    /**
     * تحويل string من IDs مفصولة بفاصلة إلى array
     */
    private function getEmployeeIdsArray(?string $idsString): array
    {
        if (empty($idsString)) {
            return [];
        }

        return array_filter(
            array_map('intval', explode(',', $idsString)),
            fn($id) => $id > 0
        );
    }

    /**
     * الحصول على معلومات الموظفين من IDs
     */
    private function getEmployeesInfo(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return User::whereIn('user_id', $ids)
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->user_id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => trim($user->first_name . ' ' . $user->last_name) ?: 'غير محدد',
                    'email' => $user->email,
                ];
            })
            ->toArray();
    }
}
