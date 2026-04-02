<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نموذج خيارات العقد (البدلات والخصومات)
 * Contract Options Model (Allowances and Deductions)
 * 
 * @property int $contract_option_id
 * @property int|null $company_id
 * @property int $user_id
 * @property string|null $salay_type allowance|statutory|commission|other_payment
 * @property int $contract_tax_option
 * @property int $is_fixed
 * @property string|null $option_title
 * @property float $contract_amount
 */
class ContractOption extends Model
{
    protected $table = 'ci_contract_options';
    protected $primaryKey = 'contract_option_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'salay_type',
        'contract_tax_option',
        'is_fixed',
        'option_title',
        'contract_amount',
    ];

    protected $casts = [
        'contract_option_id' => 'integer',
        'company_id' => 'integer',
        'user_id' => 'integer',
        'contract_tax_option' => 'integer',
        'is_fixed' => 'integer',
        'contract_amount' => 'float',
    ];

    /**
     * Scope: البدلات فقط
     */
    public function scopeAllowances($query)
    {
        return $query->where('salay_type', '!=', 'statutory');
    }

    /**
     * Scope: الخصومات النظامية فقط
     */
    public function scopeStatutory($query)
    {
        return $query->where('salay_type', 'statutory');
    }

    /**
     * Scope: حسب الشركة
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * العلاقة مع الشركة
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }
}
