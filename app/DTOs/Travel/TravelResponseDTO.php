<?php

namespace App\DTOs\Travel;

use App\Models\Travel;

class TravelResponseDTO
{
    public function __construct(
        public readonly int $travelId,
        public readonly int $companyId,
        public readonly int $employeeId,
        public readonly string $employeeName,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $associatedGoals,
        public readonly string $visitPurpose,
        public readonly string $visitPlace,
        public readonly int $travelMode,
        public readonly string $travelModeText,
        public readonly ?int $arrangementType,
        public readonly ?string $arrangementTypeName,
        public readonly ?float $expectedBudget,
        public readonly ?float $actualBudget,
        public readonly ?string $description,
        public readonly int $status,
        public readonly string $statusText,
        public readonly ?int $addedBy,
        public readonly string $createdAt,
        public readonly ?array $employee = null,
        public readonly ?array $approvals = null,
    ) {}

    public static function fromModel(Travel $travel): self
    {
        // Load relationships if not already loaded
        if (!$travel->relationLoaded('employee')) {
            $travel->load('employee');
        }
        if (!$travel->relationLoaded('approvals')) {
            $travel->load('approvals.staff');
        }
        if (!$travel->relationLoaded('arrangementType')) {
            $travel->load('arrangementType');
        }

        $employee = $travel->employee ? [
            'user_id' => $travel->employee->user_id,
            'first_name' => $travel->employee->first_name,
            'last_name' => $travel->employee->last_name,
            'email' => $travel->employee->email,
            'full_name' => $travel->employee->full_name,
        ] : null;

        $approvals = $travel->approvals->map(function ($approval) {
            return [
                'staff_approval_id' => $approval->staff_approval_id,
                'staff_id' => $approval->staff_id,
                'staff_name' => $approval->staff ? $approval->staff->full_name : null,
                'status' => $approval->status,
                'approval_level' => $approval->approval_level,
                'updated_at' => $approval->updated_at,
            ];
        })->toArray();

        return new self(
            travelId: $travel->travel_id,
            companyId: $travel->company_id,
            employeeId: $travel->employee_id,
            employeeName: $travel->employee ?
                ($travel->employee->first_name . ' ' . $travel->employee->last_name) : 'غير محدد',
            startDate: $travel->start_date,
            endDate: $travel->end_date,
            associatedGoals: $travel->associated_goals,
            visitPurpose: $travel->visit_purpose,
            visitPlace: $travel->visit_place,
            travelMode: $travel->travel_mode,
            travelModeText: self::getTravelModeText($travel->travel_mode),
            arrangementType: $travel->arrangement_type,
            arrangementTypeName: $travel->arrangementType ? $travel->arrangementType->category_name : null,
            expectedBudget: $travel->expected_budget ? (float) $travel->expected_budget : null,
            actualBudget: $travel->actual_budget ? (float) $travel->actual_budget : null,
            description: $travel->description,
            status: $travel->status,
            statusText: self::getStatusText($travel->status),
            addedBy: $travel->added_by,
            createdAt: $travel->created_at,
            employee: $employee,
            approvals: $approvals,
        );
    }

    private static function getTravelModeText(int $mode): string
    {
        return match ($mode) {
            1 => 'Bus',
            2 => 'Train',
            3 => 'Plane',
            4 => 'Taxi',
            5 => 'Rental Car',
            default => 'Unknown',
        };
    }

    private static function getStatusText(int $status): string
    {
        return match ($status) {
            0 => 'Pending',
            1 => 'Approved',
            2 => 'Rejected',
            default => 'Unknown',
        };
    }

    public function toArray(): array
    {
        return [
            'travel_id' => $this->travelId,
            'company_id' => $this->companyId,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'associated_goals' => $this->associatedGoals,
            'visit_purpose' => $this->visitPurpose,
            'visit_place' => $this->visitPlace,
            'travel_mode' => $this->travelMode,
            'travel_mode_text' => $this->travelModeText,
            'arrangement_type' => $this->arrangementType,
            'arrangement_type_name' => $this->arrangementTypeName,
            'expected_budget' => $this->expectedBudget,
            'actual_budget' => $this->actualBudget,
            'description' => $this->description,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'added_by' => $this->addedBy,
            'created_at' => $this->createdAt,
            'employee' => $this->employee,
            'approvals' => $this->approvals,
        ];
    }
}
