<?php

namespace Database\Factories;

use App\Models\UserDetails;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserDetails>
 */
class UserDetailsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'user_id' => 1,
            'employee_id' => 'EMP' . str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'reporting_manager' => 1,
            'department_id' => 1,
            'designation_id' => 1,
            'branch_id' => 1,
            'office_shift_id' => 1,
            'basic_salary' => fake()->numberBetween(3000, 15000),
            'hourly_rate' => 0,
            'salay_type' => 1,
            'leave_categories' => 'all',
            'date_of_joining' => now()->subDays(fake()->numberBetween(30, 365))->format('Y-m-d'),
            'contract_end' => 0,
            'bank_name' => 1,
            'ml_tax_category' => 1,
            'ml_empployee_epf_rate' => '0',
            'ml_empployer_epf_rate' => '0',
            'ml_eis_contribution' => 1,
            'ml_socso_category' => 1,
            'ml_pcb_socso' => '2021',
            'ml_hrdf' => 1,
            'ml_tax_citizenship' => 1,
            'zakat_fund' => 0,
            'job_type' => 1,
            'is_accrual_pause' => 0,
            'is_work_from_home' => 0,
            'is_eqama' => 1,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}