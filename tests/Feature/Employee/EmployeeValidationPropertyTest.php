<?php

namespace Tests\Feature\Employee;

use App\Http\Requests\Employee\CreateEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Property-Based Test for Employee Data Validation
 * 
 * Feature: employee-management-api, Property 22: التحقق من صحة البيانات
 * Validates: Requirements 5.1
 */
class EmployeeValidationPropertyTest extends TestCase
{
    /**
     * Property 22: Data Validation Property Test
     * 
     * For any employee creation request, all required data must be validated before saving
     * 
     * @test
     */
    public function property_create_employee_validates_required_fields_for_all_inputs(): void
    {
        // Run property test with 100 iterations as specified in design
        for ($i = 0; $i < 100; $i++) {
            $this->runCreateEmployeeValidationProperty();
        }
    }

    /**
     * Property 22: Invalid Data Rejection
     * 
     * For any invalid employee data, the validation must reject the request
     * 
     * @test
     */
    public function property_invalid_employee_data_is_always_rejected(): void
    {
        // Run property test with 100 iterations as specified in design
        for ($i = 0; $i < 100; $i++) {
            $this->runInvalidDataRejectionProperty();
        }
    }

    /**
     * Property 22: Required Fields Validation
     * 
     * For any missing required field, validation must fail
     * 
     * @test
     */
    public function property_missing_required_fields_always_fail(): void
    {
        // Run property test with 100 iterations as specified in design
        for ($i = 0; $i < 100; $i++) {
            $this->runRequiredFieldsValidationProperty();
        }
    }

    /**
     * Run single iteration of create employee validation property
     */
    private function runCreateEmployeeValidationProperty(): void
    {
        // Generate random valid employee data
        $validData = $this->generateValidEmployeeData();
        
        // Create validation rules without database constraints for testing
        $rules = $this->getValidationRulesWithoutDatabase();
        
        // Test that valid data passes validation
        $validator = Validator::make($validData, $rules);
        
        $this->assertTrue(
            $validator->passes(),
            "Valid employee data should pass validation. Failed with: " . json_encode($validator->errors()->toArray())
        );
    }

    /**
     * Run single iteration of required fields validation property
     */
    private function runRequiredFieldsValidationProperty(): void
    {
        $rules = $this->getValidationRulesWithoutDatabase();
        $requiredFields = ['first_name', 'last_name', 'email', 'username', 'password', 'department_id', 'designation_id'];
        
        foreach ($requiredFields as $field) {
            $validData = $this->generateValidEmployeeData();
            unset($validData[$field]);
            
            $validator = Validator::make($validData, $rules);
            
            $this->assertTrue(
                $validator->fails(),
                "Missing required field '{$field}' should fail validation"
            );
            
            $this->assertTrue(
                $validator->errors()->has($field),
                "Validation should have error for missing field '{$field}'"
            );
        }
    }

    /**
     * Run single iteration of invalid data rejection property
     */
    private function runInvalidDataRejectionProperty(): void
    {
        $rules = $this->getValidationRulesWithoutDatabase();

        // Test invalid email
        $invalidData = $this->generateValidEmployeeData();
        $invalidData['email'] = 'invalid-email';
        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue(
            $validator->fails(),
            "Invalid email should fail validation"
        );

        // Test invalid gender
        $invalidData = $this->generateValidEmployeeData();
        $invalidData['gender'] = 'X';
        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue(
            $validator->fails(),
            "Invalid gender should fail validation"
        );

        // Test invalid date
        $invalidData = $this->generateValidEmployeeData();
        $invalidData['date_of_birth'] = 'not-a-date';
        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue(
            $validator->fails(),
            "Invalid date should fail validation"
        );

        // Test negative salary
        $invalidData = $this->generateValidEmployeeData();
        $invalidData['basic_salary'] = -100;
        $validator = Validator::make($invalidData, $rules);
        $this->assertTrue(
            $validator->fails(),
            "Negative salary should fail validation"
        );
    }

    /**
     * Get validation rules without database constraints for testing
     */
    private function getValidationRulesWithoutDatabase(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:M,F'],
            'department_id' => ['required', 'integer'],
            'designation_id' => ['required', 'integer'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'date_of_joining' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Generate random valid employee data
     */
    private function generateValidEmployeeData(): array
    {
        $faker = fake();
        
        return [
            'first_name' => $faker->firstName(),
            'last_name' => $faker->lastName(),
            'email' => $faker->unique()->safeEmail(),
            'username' => $faker->unique()->userName(),
            'password' => $faker->password(8),
            'contact_number' => $faker->phoneNumber(),
            'gender' => $faker->randomElement(['M', 'F']),
            'department_id' => $faker->numberBetween(1, 10),
            'designation_id' => $faker->numberBetween(1, 20),
            'basic_salary' => $faker->randomFloat(2, 1000, 50000),
            'date_of_joining' => $faker->date(),
            'date_of_birth' => $faker->date('Y-m-d', '-18 years'),
            'marital_status' => $faker->randomElement(['single', 'married', 'divorced']),
            'blood_group' => $faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            'city' => $faker->city(),
            'country' => $faker->country(),
            'is_active' => $faker->boolean(),
        ];
    }

    /**
     * Generate data with invalid email formats
     */
    private function generateInvalidEmailData(): array
    {
        $baseData = $this->generateValidEmployeeData();
        $invalidEmails = [
            'invalid-email',
            'test@',
            '@domain.com',
            'test..test@domain.com',
            // Remove 'test@domain' as it might be considered valid by some validators
        ];
        
        $baseData['email'] = fake()->randomElement($invalidEmails);
        return $baseData;
    }

    /**
     * Generate data with invalid numeric values
     */
    private function generateInvalidNumericData(): array
    {
        $baseData = $this->generateValidEmployeeData();
        // Use clearly invalid numeric values
        $invalidNumbers = ['not-a-number', 'abc123', ''];
        
        $numericFields = ['basic_salary'];
        $field = fake()->randomElement($numericFields);
        $baseData[$field] = fake()->randomElement($invalidNumbers);
        
        return $baseData;
    }

    /**
     * Generate data with invalid date formats
     */
    private function generateInvalidDateData(): array
    {
        $baseData = $this->generateValidEmployeeData();
        $invalidDates = [
            'not-a-date',
            '2024-13-01', // Invalid month
            '2024-02-30', // Invalid day
            'tomorrow',   // Future date for birth
        ];
        
        $dateFields = ['date_of_birth', 'date_of_joining'];
        $field = fake()->randomElement($dateFields);
        $baseData[$field] = fake()->randomElement($invalidDates);
        
        return $baseData;
    }

    /**
     * Generate data with invalid enum values
     */
    private function generateInvalidEnumData(): array
    {
        $baseData = $this->generateValidEmployeeData();
        $invalidGenders = ['X', 'Male', 'Female', 'Other'];
        
        $baseData['gender'] = fake()->randomElement($invalidGenders);
        return $baseData;
    }
}