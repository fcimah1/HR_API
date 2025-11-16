<?php

namespace App\Http\Requests\Employee;

use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization will be handled in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Required fields
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('ci_erp_users', 'email')->where(function ($query) {
                    return $query->where('company_id', $this->user()->company_id);
                }),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('ci_erp_users', 'username')->where(function ($query) {
                    return $query->where('company_id', $this->user()->company_id);
                }),
            ],
            'password' => 'required|string|min:6|max:255',
            
            // Optional basic info
            'user_type' => 'sometimes|string|in:admin,hr,manager,employee',
            'user_role_id' => 'sometimes|integer|min:1',
            'contact_number' => 'sometimes|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'gender' => 'sometimes|string|in:male,female',
            'address_1' => 'sometimes|string|max:500',
            'address_2' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|max:255',
            'zipcode' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:255',
            'profile_photo' => 'sometimes|string|max:500',
            
            // Employee details
            'employee_id' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\-_]+$/',
                Rule::unique('ci_erp_users_details', 'employee_id')->where(function ($query) {
                    return $query->where('company_id', $this->user()->company_id);
                }),
            ],
            'reporting_manager' => 'sometimes|integer|exists:ci_erp_users,user_id',
            'department_id' => 'sometimes|integer|exists:ci_departments,department_id',
            'designation_id' => 'sometimes|integer|exists:ci_designations,designation_id',
            'office_shift_id' => 'sometimes|integer',
            'basic_salary' => 'sometimes|numeric|min:0|max:9999999.99',
            'hourly_rate' => 'sometimes|numeric|min:0|max:9999.99',
            'salary_type' => 'sometimes|integer|in:1,2,3', // 1=monthly, 2=hourly, 3=daily
            'leave_categories' => 'sometimes|string|max:255',
            'role_description' => 'sometimes|string|max:1000',
            'date_of_joining' => 'sometimes|date_format:Y-m-d|before_or_equal:today',
            'date_of_birth' => 'sometimes|date_format:Y-m-d|before:today|after:1900-01-01',
            'marital_status' => 'sometimes|integer|in:1,2,3,4', // 1=single, 2=married, 3=divorced, 4=widowed
            'blood_group' => 'sometimes|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'bio' => 'sometimes|string|max:1000',
            'experience' => 'sometimes|integer|min:0|max:50',
            
            // Bank details
            'account_title' => 'sometimes|string|max:255',
            'account_number' => 'sometimes|string|max:50|regex:/^[0-9\-]+$/',
            'bank_name' => 'sometimes|integer',
            'iban' => 'sometimes|string|max:50|regex:/^[A-Z0-9]+$/',
            'swift_code' => 'sometimes|string|max:20|regex:/^[A-Z0-9]+$/',
            'bank_branch' => 'sometimes|string|max:255',
            
            // Emergency contact
            'contact_full_name' => 'sometimes|string|max:255',
            'contact_phone_no' => 'sometimes|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'contact_email' => 'sometimes|email|max:255',
            'contact_address' => 'sometimes|string|max:500',
            
            // Job details
            'job_type' => 'sometimes|integer|in:1,2,3', // 1=full-time, 2=part-time, 3=contract
            'assigned_hours' => 'sometimes|string|max:20',
            'is_work_from_home' => 'sometimes|boolean',
            'is_eqama' => 'sometimes|boolean',
            'branch_id' => 'sometimes|integer',
            'employee_idnum' => 'sometimes|string|max:155',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already registered in your company',
            'username.required' => 'Username is required',
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores and hyphens',
            'username.unique' => 'This username is already taken in your company',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 6 characters',
            'contact_number.regex' => 'Please provide a valid phone number',
            'employee_id.regex' => 'Employee ID can only contain letters, numbers, hyphens and underscores',
            'employee_id.unique' => 'This employee ID is already used in your company',
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
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
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
            Log::warning('فشل إضافة موظف', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'فشل إضافة موظف',
            'errors' => $validator->errors(),
        ], 422));
    }
}
