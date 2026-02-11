<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'employee_id' => $this->user_details?->employee_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => "{$this->first_name} {$this->last_name}",
            'email' => $this->email,
            'username' => $this->username,
            'contact_number' => $this->contact_number,
            'gender' => $this->gender,
            'profile_photo' => $this->profile_photo ? url("storage/{$this->profile_photo}") : null,
            
            // معلومات الوظيفة
            'department' => new DepartmentResource($this->whenLoaded('user_details.department')),
            'designation' => new DesignationResource($this->whenLoaded('user_details.designation')),
            'basic_salary' => $this->user_details?->basic_salary,
            'hourly_rate' => $this->user_details?->hourly_rate,
            'salary_type' => $this->user_details?->salary_type,
            'currency' => $this->user_details?->currency,
            
            // التواريخ
            'date_of_joining' => $this->user_details?->date_of_joining,
            'date_of_birth' => $this->user_details?->date_of_birth,
            'date_of_leaving' => $this->user_details?->date_of_leaving,
            
            // معلومات إضافية
            'marital_status' => $this->user_details?->marital_status,
            'blood_group' => $this->user_details?->blood_group,
            'bio' => $this->user_details?->bio,
            'experience' => $this->user_details?->experience,
            
            // العنوان
            'address_1' => $this->user_details?->address_1,
            'address_2' => $this->user_details?->address_2,
            'city' => $this->user_details?->city,
            'state' => $this->user_details?->state,
            'zipcode' => $this->user_details?->zipcode,
            'country' => $this->user_details?->country,
            
            // معلومات البنك
            'account_title' => $this->user_details?->account_title,
            'account_number' => $this->user_details?->account_number,
            'bank_name' => $this->user_details?->bank_name,
            'iban' => $this->user_details?->iban,
            'swift_code' => $this->user_details?->swift_code,
            'bank_branch' => $this->user_details?->bank_branch,
            
            // معلومات جهة الاتصال
            'contact_full_name' => $this->user_details?->contact_full_name,
            'contact_phone_no' => $this->user_details?->contact_phone_no,
            'contact_email' => $this->user_details?->contact_email,
            'contact_address' => $this->user_details?->contact_address,
            
            // معلومات الهوية
            'employee_idnum' => $this->user_details?->employee_idnum,
            'passport_no' => $this->user_details?->passport_no,
            'passport_date' => $this->user_details?->passport_date,
            
            // الشبكات الاجتماعية
            'fb_profile' => $this->user_details?->fb_profile,
            'twitter_profile' => $this->user_details?->twitter_profile,
            'gplus_profile' => $this->user_details?->gplus_profile,
            'linkedin_profile' => $this->user_details?->linkedin_profile,
            
            // معلومات الحالة
            'is_active' => (bool) $this->is_active,
            'last_login_date' => $this->last_login_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // معلومات إضافية حسب الطلب
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'leave_balance' => $this->when($this->relationLoaded('leaveBalance'), 
                fn() => $this->leaveBalance),
            'attendance_records' => $this->when($this->relationLoaded('attendanceRecords'),
                fn() => $this->attendanceRecords),
            'salary_details' => $this->when($this->relationLoaded('salaryDetails'),
                fn() => $this->salaryDetails),
        ];
    }
}
