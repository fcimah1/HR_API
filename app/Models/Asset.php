<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

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
        'assets_id' => 'integer',
        'assets_category_id' => 'integer',
        'brand_id' => 'integer',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'is_working' => 'boolean',
    ];

    /**
     * Get the employee assigned to this asset
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the asset category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'assets_category_id', 'constants_id')
            ->where('type', ErpConstant::TYPE_ASSETS_CATEGORY);
    }

    /**
     * Get the asset brand
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'brand_id', 'constants_id')
            ->where('type', ErpConstant::TYPE_ASSETS_BRAND);
    }

    /**
     * Get the asset history
     */
    public function history(): HasMany
    {
        return $this->hasMany(AssetHistory::class, 'asset_id', 'assets_id');
    }

    /**
     * Scope to filter by company
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by company name
     */
    public function scopeForCompanyName(Builder $query, string $companyName): Builder
    {
        // Get company IDs that match the company name
        $companyIds = User::where('company_name', $companyName)
            ->distinct()
            ->pluck('company_id')
            ->toArray();

        if (empty($companyIds)) {
            // Return empty result if no company IDs found
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('company_id', $companyIds);
    }

    /**
     * Scope to filter assets assigned to an employee
     */
    public function scopeAssignedToEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter unassigned assets
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where('employee_id', 0);
    }

    /**
     * Scope to filter working assets
     */
    public function scopeWorking(Builder $query): Builder
    {
        return $query->where('is_working', 1);
    }

    /**
     * Scope to filter non-working assets
     */
    public function scopeNonWorking(Builder $query): Builder
    {
        return $query->where('is_working', 0);
    }

    /**
     * Check if asset is assigned
     */
    public function isAssigned(): bool
    {
        return $this->employee_id > 0;
    }

    /**
     * Check if asset is working
     */
    public function isWorking(): bool
    {
        return $this->is_working === 1 || $this->is_working === true;
    }

    /**
     * Check if warranty is still valid
     */
    public function isWarrantyValid(): bool
    {
        if (empty($this->warranty_end_date)) {
            return false;
        }

        try {
            $warrantyDate = \Carbon\Carbon::parse($this->warranty_end_date);
            return $warrantyDate->isFuture();
        } catch (\Exception $e) {
            return false;
        }
    }
}

