<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceSalary extends Model
{
    use HasFactory;

    protected $table = 'ci_advance_salary';
    protected $primaryKey = 'advance_salary_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'salary_type',
        'month_year',
        'advance_amount',
        'one_time_deduct',
        'monthly_installment',
        'total_paid',
        'reason',
        'status',
        'is_deducted_from_salary',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'advance_amount' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'status' => 'integer',
        'is_deducted_from_salary' => 'integer',
    ];

    /**
     * Get the employee who requested the advance/loan
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the approvals for this advance salary request
     */
    public function approvals()
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'advance_salary_id')
            ->where('module_option', 'advance_salary_settings');
    }

    /**
     * Check if this is a loan request
     */
    public function isLoan(): bool
    {
        return $this->salary_type === 'loan';
    }

    /**
     * Check if this is an advance salary request
     */
    public function isAdvance(): bool
    {
        return $this->salary_type === 'advance';
    }

    /**
     * Check if the request is pending
     */
    public function isPending(): bool
    {
        return $this->status === 0;
    }

    /**
     * Check if the request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 1;
    }

    /**
     * Check if the request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 2;
    }

    /**
     * Get the remaining amount to be paid
     */
    public function getRemainingAmount(): float
    {
        return (float) ($this->advance_amount - $this->total_paid);
    }

    /**
     * Get status text in Arabic
     */
    public function getStatusText(): string
    {
        return match ($this->status) {
            0 => 'قيد الانتظار',
            1 => 'موافق عليه',
            2 => 'مرفوض',
            default => 'غير معروف',
        };
    }

    /**
     * Get type text in Arabic
     */
    public function getTypeText(): string
    {
        return match ($this->salary_type) {
            'loan' => 'قرض',
            'advance' => 'سلفة',
            default => 'غير معروف',
        };
    }

    /**
     * Get one time deduct text in Arabic
     */
    public function getOneTimeDeductText(): string
    {
        return $this->one_time_deduct === '1' ? 'نعم' : 'لا';
    }
}
