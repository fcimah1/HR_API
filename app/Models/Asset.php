<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasFactory;

    protected $table = 'ci_assets';
    protected $primaryKey = 'assets_id';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'assets_category_id',
        'brand_id',
        'company_id',
        'employee_id',
        'company_asset_code',
        'name',
        'purchase_date',
        'invoice_number',
        'manufacturer',
        'serial_number',
        'warranty_end_date',
        'asset_note',
        'asset_image',
        'is_working',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'assets_category_id' => 'integer',
        'brand_id' => 'integer',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'is_working' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Get the employee who owns the asset.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the company that owns the asset.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Get the asset category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'assets_category_id', 'constants_id');
    }

    /**
     * Get the brand.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'brand_id', 'constants_id');
    }

    /**
     * Scope for working assets.
     */
    public function scopeWorking($query)
    {
        return $query->where('is_working', 1);
    }

    /**
     * Scope for a specific employee.
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope for a specific company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Check if asset is assigned to an employee.
     */
    public function isAssigned(): bool
    {
        return !is_null($this->employee_id) && $this->employee_id > 0;
    }
}
