<?php

namespace App\DTOs\Travel;

use App\Enums\NumericalStatusEnum;
use App\Enums\TravelModeEnum;
use App\Enums\TravelStatusEnum;
use App\Models\Travel;

class CreateTravelDTO
{
    public function __construct(
        public int $employee_id,
        public string $start_date,
        public string $end_date,
        public string $visit_purpose,
        public string $visit_place,
        public int $travel_mode,
        public int $arrangement_type,
        public float $expected_budget,
        public float $actual_budget,
        public ?string $description = null,
        public ?array $associated_goals = null,
        public int $status = TravelStatusEnum::PENDING->value,
        public ?int $added_by = null,
        public ?int $company_id = null
    ) {}

    public static function fromRequest($request, int $employeeId, int $companyId, int $addedBy): self
    {
        return new self(
            employee_id: $request->input('employee_id', $employeeId), // يمكن للمدير تحديد موظف آخر
            start_date: $request->input('start_date'),
            end_date: $request->input('end_date'),
            visit_purpose: $request->input('visit_purpose'),
            visit_place: $request->input('visit_place'),
            travel_mode: $request->input('travel_mode'),
            arrangement_type: $request->input('arrangement_type'),
            expected_budget: $request->input('expected_budget'),
            actual_budget: $request->input('actual_budget'),
            description: $request->input('description'),
            associated_goals: is_string($request->input('associated_goals')) 
                ? explode(',', $request->input('associated_goals'))
                : $request->input('associated_goals'),
            status: TravelStatusEnum::PENDING->value,
            added_by: $addedBy,
            company_id: $companyId
        );
    }

    public function toArray(): array
    {
        return [
            'employee_id' => $this->employee_id,
            'company_id' => $this->company_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'visit_purpose' => $this->visit_purpose,
            'visit_place' => $this->visit_place,
            'travel_mode' => $this->travel_mode,
            'arrangement_type' => $this->arrangement_type,
            'expected_budget' => $this->expected_budget,
            'actual_budget' => $this->actual_budget,
            'description' => $this->description,
            'associated_goals' => $this->associated_goals,
            'status' => $this->status,
            'added_by' => $this->added_by,
            'created_at' => now(),
        ];
    }
}
