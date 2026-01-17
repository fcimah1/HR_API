<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    use HasFactory;

    protected $table = 'ci_awards';
    protected $primaryKey = 'award_id';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'award_type_id',
        'gift_item',
        'cash_price',
        'award_month_year',
        'created_at',
        'company_id',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'award_type_id' => 'integer',
        'company_id' => 'integer',
        'cash_price' => 'decimal:2',
    ];

    /**
     * Get the employee associated with the award.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the award type constant.
     */
    public function awardType(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'award_type_id', 'constants_id');
    }

    /**
     * Scope a query to only include awards for a specific company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
