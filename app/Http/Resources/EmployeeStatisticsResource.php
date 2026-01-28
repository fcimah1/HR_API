<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_employees' => $this->resource['total_employees'],
            'active_employees' => $this->resource['active_employees'],
            'inactive_employees' => $this->resource['inactive_employees'],
            'departments_count' => $this->resource['departments_count'],
            'designations_count' => $this->resource['designations_count'],
            'average_salary' => $this->resource['average_salary'],
            'total_salary_cost' => $this->resource['total_salary_cost'] ?? 0,

            'employees_by_department' => collect($this->resource['employees_by_department'] ?? [])
                ->map(fn($item) => [
                    'department_id' => $item['department_id'],
                    'department_name' => $item['department_name'],
                    'count' => $item['total_employees'] ?? 0,
                    'total_employees' => $item['total_employees'] ?? 0,
                    'active_employees' => $item['active_employees'] ?? 0,
                    'inactive_employees' => $item['inactive_employees'] ?? 0,
                    'percentage' => $this->resource['total_employees'] > 0
                        ? round((($item['total_employees'] ?? 0) / $this->resource['total_employees']) * 100, 2)
                        : 0
                ]),

            'employees_by_designation' => collect($this->resource['employees_by_designation'] ?? [])
                ->map(fn($item) => [
                    'designation_id' => $item['designation_id'],
                    'designation_name' => $item['designation_name'],
                    'hierarchy_level' => $item['hierarchy_level'],
                    'count' => $item['total_employees'] ?? 0,
                    'total_employees' => $item['total_employees'] ?? 0,
                    'active_employees' => $item['active_employees'] ?? 0,
                    'inactive_employees' => $item['inactive_employees'] ?? 0,
                    'percentage' => $this->resource['total_employees'] > 0
                        ? round((($item['total_employees'] ?? 0) / $this->resource['total_employees']) * 100, 2)
                        : 0
                ]),

            'employees_by_hierarchy' => collect($this->resource['employees_by_hierarchy'] ?? [])
                ->map(fn($item) => [
                    'hierarchy_level' => $item['hierarchy_level'],
                    'level_name' => $item['level_name'] ?? 'غير محدد',
                    'count' => $item['total_employees'] ?? 0,
                    'total_employees' => $item['total_employees'] ?? 0,
                    'active_employees' => $item['active_employees'] ?? 0,
                    'inactive_employees' => $item['inactive_employees'] ?? 0,
                    'percentage' => $this->resource['total_employees'] > 0
                        ? round((($item['total_employees'] ?? 0) / $this->resource['total_employees']) * 100, 2)
                        : 0
                ]),

            'by_gender' => $this->resource['by_gender'] ?? [],
            'by_age_group' => $this->resource['by_age_group'] ?? [],
            'salary_statistics' => $this->resource['salary_statistics'] ?? [],
            'recent_hires' => $this->resource['recent_hires'] ?? 0,
        ];
    }
}
