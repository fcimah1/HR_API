<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDetails extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ci_erp_users_details';
    protected $primaryKey = 'staff_details_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'employee_id',
        'reporting_manager',
        'department_id',
        'designation_id',
        'office_shift_id',
        'basic_salary',
        'hourly_rate',
        'salay_type',
        'leave_categories',
        'role_description',
        'date_of_joining',
        'contract_end',
        'date_of_leaving',
        'date_of_birth',
        'marital_status',
        'religion_id',
        'blood_group',
        'citizenship_id',
        'bio',
        'experience',
        'fb_profile',
        'twitter_profile',
        'gplus_profile',
        'linkedin_profile',
        'account_title',
        'account_number',
        'bank_name',
        'iban',
        'swift_code',
        'bank_branch',
        'default_language',
        'contact_full_name',
        'contact_phone_no',
        'contact_email',
        'contact_address',
        'ml_tax_category',
        'ml_empployee_epf_rate',
        'ml_empployer_epf_rate',
        'ml_eis_contribution',
        'ml_socso_category',
        'ml_pcb_socso',
        'ml_hrdf',
        'ml_tax_citizenship',
        'zakat_fund',
        'job_type',
        'assigned_hours',
        'leave_options',
        'approval_levels',
        'approval_level01',
        'approval_level02',
        'approval_level03',
        'not_part_of_orgchart',
        'not_part_of_system_reports',
        'is_accrual_pause',
        'is_work_from_home',
        'is_eqama',
        'pause_start_date',
        'pause_start_end',
        'created_at',
        'employee_idnum',
        'branch_id',
        'contract_date_eqama',
        'salary_payment_method',
        'currency_id',
        'contract_option_id',
        'biotime_id',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'zakat_fund' => 'decimal:2',
        'contract_end' => 'boolean',
        'is_accrual_pause' => 'boolean',
        'is_work_from_home' => 'boolean',
        'is_eqama' => 'boolean',
    ];

    /**
     * Get the user that owns the details
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // get permissions of the user
    /**
     * Get the reporting manager
     */

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id', 'designation_id');
    }

    public function officeShift(): BelongsTo
    {
        return $this->belongsTo(OfficeShift::class, 'office_shift_id', 'office_shift_id');
    }

    /**
     * البحث عن الموظف باستخدام المفتاح المركب من جهاز البصمة
     * Find user by biometric composite key
     */
    public function scopeByBiometricId($query, int $companyId, int $branchId, string $employeeIdnum)
    {
        $query->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('employee_idnum', $employeeIdnum);

        return $query;
    }
}
