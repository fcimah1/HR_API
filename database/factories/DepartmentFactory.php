<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'department_name' => $this->faker->randomElement([
                'قسم الموارد البشرية',
                'قسم المحاسبة',
                'قسم التسويق',
                'قسم تقنية المعلومات',
                'قسم المبيعات',
                'قسم خدمة العملاء',
                'قسم الإنتاج',
                'قسم الجودة'
            ]),
            'department_head' => 0,
            'company_id' => 1,
            'added_by' => 1,
            'created_at' => now()->format('Y-m-d H:i:s')
        ];
    }
}