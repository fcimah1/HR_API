<?php

namespace Database\Factories;

use App\Models\UserDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserDetailFactory extends Factory
{

    public function definition(): array
    {
        return [
            'employee_id' => 'EMP' . $this->faker->unique()->numberBetween(1000, 9999),
            'designation_id' => 1,
            'department_id' => 1,
            'branch_id' => 1,
            'salary' => $this->faker->numberBetween(3000, 15000),
            'hourly_rate' => $this->faker->numberBetween(20, 100),
            'salary_type' => $this->faker->randomElement(['monthly', 'hourly']),
            'currency' => 'SAR',
            'date_of_joining' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'marital_status' => $this->faker->randomElement(['single', 'married', 'divorced', 'widowed']),
            'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'address_1' => $this->faker->address(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'country' => 'Saudi Arabia',
            'zipcode' => $this->faker->postcode(),
            'contact_full_name' => $this->faker->name(),
            'contact_phone_no' => $this->faker->phoneNumber(),
            'contact_email' => $this->faker->email(),
            'employee_idnum' => $this->faker->unique()->numerify('##########'),
            'passport_no' => $this->faker->unique()->bothify('?#######'),
            'passport_date' => $this->faker->dateTimeBetween('+1 year', '+10 years')->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
}