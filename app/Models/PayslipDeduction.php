<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نموذج خصومات القسيمة
 * Payslip Deduction Model
 * 
 * @property int $payslip_deduction_id
 * @property int $payslip_id
 * @property int $staff_id
 * @property int $is_fixed
 * @property string $pay_title
 * @property float $pay_amount
 * @property string $salary_month
 * @property string $created_at
 * @property int|null $contract_option_id
 */
class PayslipDeduction extends Model
{
    protected $table = 'ci_payslip_statutory_deductions';
    protected $primaryKey = 'payslip_deduction_id';
    public $timestamps = false;

    protected $fillable = [
        'payslip_id',
        'staff_id',
        'is_fixed',
        'pay_title',
        'pay_amount',
        'salary_month',
        'created_at',
        'contract_option_id',
    ];

    protected $casts = [
        'payslip_deduction_id' => 'integer',
        'payslip_id' => 'integer',
        'staff_id' => 'integer',
        'is_fixed' => 'integer',
        'pay_amount' => 'float',
        'contract_option_id' => 'integer',
    ];

    /**
     * القسيمة المرتبطة
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class, 'payslip_id', 'payslip_id');
    }

    /**
     * خيار العقد (نوع الخصم)
     */
    public function contractOption(): BelongsTo
    {
        return $this->belongsTo(ContractOption::class, 'contract_option_id', 'contract_option_id');
    }
}
