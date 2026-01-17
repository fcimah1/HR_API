<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نموذج قسائم الرواتب
 * Payslip Model
 * 
 * @property int $payslip_id
 * @property string $payslip_key
 * @property int $company_id
 * @property int $staff_id
 * @property int|null $contract_option_id
 * @property string $salary_month
 * @property int $wages_type
 * @property string $payslip_type
 * @property float $basic_salary
 * @property float $daily_wages
 * @property string $hours_worked
 * @property float $total_allowances
 * @property float $total_commissions
 * @property float $total_statutory_deductions
 * @property float $total_other_payments
 * @property float $net_salary
 * @property int $payment_method
 * @property string $pay_comments
 * @property int $is_payment
 * @property string $year_to_date
 * @property int $is_advance_salary_deduct
 * @property float|null $advance_salary_amount
 * @property int $is_loan_deduct
 * @property float $loan_amount
 * @property float $unpaid_leave_days
 * @property float $unpaid_leave_deduction
 * @property int $status
 * @property string $created_at
 * @property string|null $salary_payment_method
 */
class Payslip extends Model
{
    protected $table = 'ci_payslips';
    protected $primaryKey = 'payslip_id';
    public $timestamps = false;

    protected $fillable = [
        'payslip_key',
        'company_id',
        'staff_id',
        'contract_option_id',
        'salary_month',
        'wages_type',
        'payslip_type',
        'basic_salary',
        'daily_wages',
        'hours_worked',
        'total_allowances',
        'total_commissions',
        'total_statutory_deductions',
        'total_other_payments',
        'net_salary',
        'payment_method',
        'pay_comments',
        'is_payment',
        'year_to_date',
        'is_advance_salary_deduct',
        'advance_salary_amount',
        'is_loan_deduct',
        'loan_amount',
        'unpaid_leave_days',
        'unpaid_leave_deduction',
        'status',
        'created_at',
        'salary_payment_method',
    ];

    protected $casts = [
        'payslip_id' => 'integer',
        'company_id' => 'integer',
        'staff_id' => 'integer',
        'contract_option_id' => 'integer',
        'wages_type' => 'integer',
        'basic_salary' => 'float',
        'daily_wages' => 'float',
        'total_allowances' => 'float',
        'total_commissions' => 'float',
        'total_statutory_deductions' => 'float',
        'total_other_payments' => 'float',
        'net_salary' => 'float',
        'payment_method' => 'integer',
        'is_payment' => 'integer',
        'is_advance_salary_deduct' => 'integer',
        'advance_salary_amount' => 'float',
        'is_loan_deduct' => 'integer',
        'loan_amount' => 'float',
        'unpaid_leave_days' => 'float',
        'unpaid_leave_deduction' => 'float',
        'status' => 'integer',
    ];

    /**
     * الموظف صاحب القسيمة
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id', 'user_id');
    }

    /**
     * الشركة
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * البدلات المرتبطة بالقسيمة
     */
    public function allowances(): HasMany
    {
        return $this->hasMany(PayslipAllowance::class, 'payslip_id', 'payslip_id');
    }

    /**
     * الخصومات المرتبطة بالقسيمة
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(PayslipDeduction::class, 'payslip_id', 'payslip_id');
    }

    /**
     * Scope: حسب الشهر
     */
    public function scopeForMonth($query, string $salaryMonth)
    {
        return $query->where('salary_month', $salaryMonth);
    }

    /**
     * Scope: حسب الشركة
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: حسب طريقة الدفع
     */
    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('salary_payment_method', $method);
    }

    /**
     * نص حالة الدفع
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            1 => 'مدفوع',
            0 => 'معلق',
            default => 'غير معروف',
        };
    }

    /**
     * نص طريقة الدفع
     */
    public function getPaymentMethodTextAttribute(): string
    {
        return match ($this->salary_payment_method) {
            'cash' => 'نقد',
            'bank' => 'إيداع',
            default => $this->salary_payment_method ?? '-',
        };
    }
}
