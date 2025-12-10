<?php

namespace App\DTOs\Travel;

class UpdateTravelDTO
{
    public function __construct(
        public ?string $start_date = null,
        public ?string $end_date = null,
        public ?string $visit_purpose = null,
        public ?string $visit_place = null,
        public ?int $travel_mode = null,
        public ?int $arrangement_type = null,
        public ?float $expected_budget = null,
        public ?float $actual_budget = null,
        public ?string $description = null,
        public ?array $associated_goals = null,
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            start_date: $request->input('start_date') ?: null,
            end_date: $request->input('end_date') ?: null,
            visit_purpose: $request->input('visit_purpose') ?: null,
            visit_place: $request->input('visit_place') ?: null,
            travel_mode: $request->input('travel_mode') ?: null,
            arrangement_type: $request->input('arrangement_type') ?: null,
            expected_budget: $request->input('expected_budget') ?: null,
            actual_budget: $request->input('actual_budget') ?: null,
            description: $request->input('description') ?: null,
            associated_goals: is_string($request->input('associated_goals')) 
                ? explode(',', $request->input('associated_goals'))
                : $request->input('associated_goals')
        );
    }

    public function toArray(): array
    {
        return array_filter([
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
        ], fn($value) => $value !== null);
    }
}
