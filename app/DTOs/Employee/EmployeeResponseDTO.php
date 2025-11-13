<?php

namespace App\DTOs\Employee;

use App\Models\User;

class EmployeeResponseDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $fullName,
        public readonly string $email,
        public readonly string $username,
        public readonly string $userType,
        public readonly ?int $userRoleId,
        public readonly ?string $contactNumber,
        public readonly ?string $gender,
        public readonly ?string $profilePhoto,
        public readonly bool $isActive,
        public readonly bool $isLoggedIn,
        public readonly ?string $lastLoginDate,
        public readonly ?string $createdAt,
        public readonly array $address,
        public readonly ?array $details = null
    ) {}

    public static function fromModel(User $user): self
    {
        $details = null;
        if ($user->relationLoaded('details') && $user->details) {
            $details = [
                'employee_id' => $user->details->employee_id,
                'reporting_manager' => $user->details->reporting_manager,
                'department_id' => $user->details->department_id,
                'designation_id' => $user->details->designation_id,
                'office_shift_id' => $user->details->office_shift_id,
                'basic_salary' => $user->details->basic_salary,
                'hourly_rate' => $user->details->hourly_rate,
                'salary_type' => $user->details->salay_type,
                'leave_categories' => $user->details->leave_categories,
                'role_description' => $user->details->role_description,
                'date_of_joining' => $user->details->date_of_joining,
                'date_of_birth' => $user->details->date_of_birth,
                'marital_status' => $user->details->marital_status,
                'blood_group' => $user->details->blood_group,
                'bio' => $user->details->bio,
                'experience' => $user->details->experience,
                'bank_details' => [
                    'account_title' => $user->details->account_title,
                    'account_number' => $user->details->account_number,
                    'bank_name' => $user->details->bank_name,
                    'iban' => $user->details->iban,
                    'swift_code' => $user->details->swift_code,
                    'bank_branch' => $user->details->bank_branch,
                ],
                'emergency_contact' => [
                    'contact_full_name' => $user->details->contact_full_name,
                    'contact_phone_no' => $user->details->contact_phone_no,
                    'contact_email' => $user->details->contact_email,
                    'contact_address' => $user->details->contact_address,
                ],
                'job_details' => [
                    'job_type' => $user->details->job_type,
                    'assigned_hours' => $user->details->assigned_hours,
                    'is_work_from_home' => (bool) $user->details->is_work_from_home,
                    'is_eqama' => (bool) $user->details->is_eqama,
                ],
                'branch_id' => $user->details->branch_id,
                'employee_idnum' => $user->details->employee_idnum,
            ];
        }

        return new self(
            userId: $user->user_id,
            firstName: $user->first_name,
            lastName: $user->last_name,
            fullName: $user->first_name . ' ' . $user->last_name,
            email: $user->email,
            username: $user->username,
            userType: $user->user_type,
            userRoleId: $user->user_role_id,
            contactNumber: $user->contact_number,
            gender: $user->gender,
            profilePhoto: $user->profile_photo,
            isActive: (bool) $user->is_active,
            isLoggedIn: (bool) $user->is_logged_in,
            lastLoginDate: $user->last_login_date,
            createdAt: $user->created_at,
            address: [
                'address_1' => $user->address_1,
                'address_2' => $user->address_2,
                'city' => $user->city,
                'state' => $user->state,
                'zipcode' => $user->zipcode,
                'country' => $user->country,
            ],
            details: $details
        );
    }

    public function toArray(): array
    {
        $data = [
            'user_id' => $this->userId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'username' => $this->username,
            'user_type' => $this->userType,
            'user_role_id' => $this->userRoleId,
            'contact_number' => $this->contactNumber,
            'gender' => $this->gender,
            'profile_photo' => $this->profilePhoto,
            'is_active' => $this->isActive,
            'is_logged_in' => $this->isLoggedIn,
            'last_login_date' => $this->lastLoginDate,
            'created_at' => $this->createdAt,
            'address' => $this->address,
        ];

        if ($this->details !== null) {
            $data['details'] = $this->details;
        }

        return $data;
    }
}
