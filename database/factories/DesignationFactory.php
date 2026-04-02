<?php

namespace Database\Factories;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

class DesignationFactory extends Factory
{
    protected $model = Designation::class;

    public function definition(): array
    {
        return [
            'designation_name' => $this->faker->randomElement([
                'المدير التنفيذي',
                'المدير العام',
                'مدير الموارد البشرية',
                'مدير المحاسبة',
                'مدير التسويق',
                'رئيس قسم',
                'مشرف',
                'موظف أول',
                'موظف',
                'متدرب'
            ]),
            'department_id' => 1,
            'hierarchy_level' => $this->faker->numberBetween(1, 5),
            'company_id' => 1,
            'description' => $this->faker->sentence(),
            'created_at' => now()->format('Y-m-d H:i:s')
        ];
    }
}