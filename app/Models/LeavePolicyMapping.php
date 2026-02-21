<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leave Policy Mapping Model
 * 
 * Maps company-specific leave type IDs to system leave types
 * Enables proper lookup of leave policies
 * 
 * @property int $mapping_id
 * @property int $company_id
 * @property int $leave_type_id
 * @property string $system_leave_type
 */
class LeavePolicyMapping extends Model
{
    protected $table = 'ci_leave_policy_mapping';
    protected $primaryKey = 'mapping_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'leave_type_id',
        'system_leave_type',
    ];

    protected $casts = [
        'mapping_id' => 'integer',
        'company_id' => 'integer',
        'leave_type_id' => 'integer',
    ];

    /**
     * Get the leave type (ErpConstant) associated with this mapping
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'leave_type_id', 'constants_id');
    }

    /**
     * Scope: Get mapping for a specific company
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: Get system default mappings
     */
    public function scopeSystemDefaults($query)
    {
        return $query->where('company_id', 0);
    }

    /**
     * Get system leave type for a given leave type ID
     */
    public static function getSystemLeaveType(int $companyId, int $leaveTypeId): ?string
    {
        // Try company-specific mapping first
        $mapping = self::where('company_id', $companyId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        // Fallback to system default
        if (!$mapping) {
            $mapping = self::where('company_id', 0)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
        }

        return $mapping?->system_leave_type;
    }
}
