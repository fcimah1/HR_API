<?php

namespace App\DTOs\Employee;

/**
 * Base DTO with common employee fields and methods
 */
abstract class BaseEmployeeDTO
{
    /**
     * Get basic user data array
     */
    protected function getBasicUserData(array $data): array
    {
        return [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'user_type' => $data['user_type'] ?? null,
            'user_role_id' => isset($data['user_role_id']) ? (int) $data['user_role_id'] : null,
            'contact_number' => $data['contact_number'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address_1' => $data['address_1'] ?? null,
            'address_2' => $data['address_2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zipcode' => $data['zipcode'] ?? null,
            'country' => $data['country'] ?? null,
            'profile_photo' => $data['profile_photo'] ?? null,
        ];
    }

    /**
     * Get employee details data array
     */
    protected function getEmployeeDetailsData(array $data): array
    {
        return [
            'employee_id' => $data['employee_id'] ?? null,
            'reporting_manager' => isset($data['reporting_manager']) ? (int) $data['reporting_manager'] : null,
            'department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null,
            'designation_id' => isset($data['designation_id']) ? (int) $data['designation_id'] : null,
            'office_shift_id' => isset($data['office_shift_id']) ? (int) $data['office_shift_id'] : null,
            'basic_salary' => isset($data['basic_salary']) ? (float) $data['basic_salary'] : null,
            'hourly_rate' => isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : null,
            'salary_type' => isset($data['salary_type']) ? (int) $data['salary_type'] : null,
            'leave_categories' => $data['leave_categories'] ?? null,
            'role_description' => $data['role_description'] ?? null,
            'date_of_joining' => $data['date_of_joining'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'marital_status' => isset($data['marital_status']) ? (int) $data['marital_status'] : null,
            'blood_group' => $data['blood_group'] ?? null,
            'bio' => $data['bio'] ?? null,
            'experience' => isset($data['experience']) ? (int) $data['experience'] : null,
            'account_title' => $data['account_title'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'bank_name' => isset($data['bank_name']) ? (int) $data['bank_name'] : null,
            'iban' => $data['iban'] ?? null,
            'swift_code' => $data['swift_code'] ?? null,
            'bank_branch' => $data['bank_branch'] ?? null,
            'default_language' => $data['default_language'] ?? null,
            'contact_full_name' => $data['contact_full_name'] ?? null,
            'contact_phone_no' => $data['contact_phone_no'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_address' => $data['contact_address'] ?? null,
            'job_type' => isset($data['job_type']) ? (int) $data['job_type'] : null,
            'assigned_hours' => $data['assigned_hours'] ?? null,
            'is_work_from_home' => isset($data['is_work_from_home']) ? (bool) $data['is_work_from_home'] : null,
            'is_eqama' => isset($data['is_eqama']) ? (bool) $data['is_eqama'] : null,
            'branch_id' => isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            'employee_idnum' => $data['employee_idnum'] ?? null,
        ];
    }

    /**
     * Filter out null values from array
     */
    protected function filterNullValues(array $data): array
    {
        return array_filter($data, fn($value) => $value !== null);
    }
}
