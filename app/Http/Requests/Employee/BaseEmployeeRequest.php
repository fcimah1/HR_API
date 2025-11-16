<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Base Employee Request with common validation rules and messages
 */
abstract class BaseEmployeeRequest extends FormRequest
{
    /**
     * Common validation rules for employee fields
     */
    protected function getCommonRules(): array
    {
        return [
            // Basic info rules
            'first_name' => 'string|max:255',
            'last_name' => 'string|max:255',
            'contact_number' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'gender' => 'string|in:male,female',
            'address_1' => 'nullable|string|max:500',
            'address_2' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zipcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|string|max:500',
            
            // Employee details rules
            'employee_id' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9\-_]+$/',
            'reporting_manager' => 'nullable|integer|exists:ci_erp_users,user_id',
            'department_id' => 'nullable|integer|exists:ci_departments,department_id',
            'designation_id' => 'nullable|integer|exists:ci_designations,designation_id',
            'office_shift_id' => 'nullable|integer',
            'basic_salary' => 'nullable|numeric|min:0|max:9999999.99',
            'hourly_rate' => 'nullable|numeric|min:0|max:9999.99',
            'salary_type' => 'nullable|integer|in:1,2,3',
            'leave_categories' => 'nullable|string|max:255',
            'role_description' => 'nullable|string|max:1000',
            'date_of_joining' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'date_of_birth' => 'nullable|date_format:Y-m-d|before:today|after:1900-01-01',
            'marital_status' => 'nullable|integer|in:1,2,3,4',
            'blood_group' => 'nullable|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'bio' => 'nullable|string|max:1000',
            'experience' => 'nullable|integer|min:0|max:50',
            
            // Bank details rules
            'account_title' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50|regex:/^[0-9\-]+$/',
            'bank_name' => 'nullable|integer',
            'iban' => 'nullable|string|max:50|regex:/^[A-Z0-9]+$/',
            'swift_code' => 'nullable|string|max:20|regex:/^[A-Z0-9]+$/',
            'bank_branch' => 'nullable|string|max:255',
            
            // Emergency contact rules
            'contact_full_name' => 'nullable|string|max:255',
            'contact_phone_no' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'contact_email' => 'nullable|email|max:255',
            'contact_address' => 'nullable|string|max:500',
            
            // Job details rules
            'job_type' => 'nullable|integer|in:1,2,3',
            'assigned_hours' => 'nullable|string|max:20',
            'is_work_from_home' => 'boolean',
            'is_eqama' => 'boolean',
            'branch_id' => 'nullable|integer',
            'employee_idnum' => 'nullable|string|max:155',
        ];
    }

    /**
     * Common error messages
     */
    protected function getCommonMessages(): array
    {
        return [
            'contact_number.regex' => 'Please provide a valid phone number',
            'employee_id.regex' => 'Employee ID can only contain letters, numbers, hyphens and underscores',
            'basic_salary.numeric' => 'Basic salary must be a valid number',
            'basic_salary.min' => 'Basic salary cannot be negative',
            'date_of_joining.date_format' => 'Date of joining must be in YYYY-MM-DD format',
            'date_of_joining.before_or_equal' => 'Date of joining cannot be in the future',
            'date_of_birth.date_format' => 'Date of birth must be in YYYY-MM-DD format',
            'date_of_birth.before' => 'Date of birth must be before today',
            'date_of_birth.after' => 'Date of birth must be after 1900',
            'blood_group.in' => 'Please select a valid blood group',
            'account_number.regex' => 'Account number can only contain numbers and hyphens',
            'iban.regex' => 'IBAN can only contain uppercase letters and numbers',
            'swift_code.regex' => 'SWIFT code can only contain uppercase letters and numbers',
            'reporting_manager.exists' => 'Selected reporting manager does not exist',
            'department_id.exists' => 'Selected department does not exist',
            'designation_id.exists' => 'Selected designation does not exist',
        ];
    }

    /**
     * Common field attributes
     */
    protected function getCommonAttributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'user_type' => 'user type',
            'user_role_id' => 'user role',
            'contact_number' => 'contact number',
            'address_1' => 'address line 1',
            'address_2' => 'address line 2',
            'employee_id' => 'employee ID',
            'reporting_manager' => 'reporting manager',
            'department_id' => 'department',
            'designation_id' => 'designation',
            'office_shift_id' => 'office shift',
            'basic_salary' => 'basic salary',
            'hourly_rate' => 'hourly rate',
            'salary_type' => 'salary type',
            'leave_categories' => 'leave categories',
            'role_description' => 'role description',
            'date_of_joining' => 'date of joining',
            'date_of_birth' => 'date of birth',
            'marital_status' => 'marital status',
            'blood_group' => 'blood group',
            'account_title' => 'account title',
            'account_number' => 'account number',
            'bank_name' => 'bank name',
            'bank_branch' => 'bank branch',
            'contact_full_name' => 'emergency contact name',
            'contact_phone_no' => 'emergency contact phone',
            'contact_email' => 'emergency contact email',
            'contact_address' => 'emergency contact address',
            'job_type' => 'job type',
            'assigned_hours' => 'assigned hours',
            'is_work_from_home' => 'work from home',
            'branch_id' => 'branch',
            'employee_idnum' => 'employee ID number',
        ];
    }

        protected function failedValidation(Validator $validator)
    {
            Log::warning('فشل تحديث بيانات الموظف', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل تحديث بيانات الموظف',
            'errors' => $validator->errors(),
        ], 422));
    }
}
