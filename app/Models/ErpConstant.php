<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpConstant extends Model
{
    use HasFactory;

    protected $table = 'ci_erp_constants';
    protected $primaryKey = 'constants_id';
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'type',
        'category_name',
        'field_one',
        'field_two',
        'field_three',
        'created_at',
    ];



    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'constants_id' => 'integer',
    ];

    /**
     * Constants for leave types
     */
    const TYPE_LEAVE_TYPE = 'leave_type';
    const TYPE_DEPARTMENT = 'department';
    const TYPE_DESIGNATION = 'designation';
    const TYPE_GENERAL = 'general';
    const TYPE_ASSETS_CATEGORY = 'assets_category';
    const TYPE_ASSETS_BRAND = 'assets_brand';
    const TYPE_AWARD_TYPE = 'award_type';
    const TYPE_RELIGION = 'religion';
    const TYPE_EXPENSE_TYPE = 'expense_type';
    const TYPE_INCOME_TYPE = 'income_type';

    /**
     * Scope for filtering by company
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for filtering by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for leave types
     */
    public function scopeLeaveTypes($query)
    {
        return $query->where('type', self::TYPE_LEAVE_TYPE);
    }

    /**
     * Get leave type name (from category_name)
     */
    public function getLeaveTypeNameAttribute(): string
    {
        return $this->category_name;
    }

    /**
     * Get leave type short name (from field_one)
     */
    public function getLeaveTypeShortNameAttribute(): ?string
    {
        $value = $this->field_one;

        if (is_null($value) || $value === '') {
            return null;
        }

        $options = @unserialize($value);

        if (is_array($options)) {
            if (array_key_exists('short_name', $options) && $options['short_name'] !== '') {
                return $options['short_name'];
            }

            return $this->category_name;
        }

        return $value;
    }

    /**
     * Get leave days (from field_two)
     */
    public function getLeaveDaysAttribute(): int
    {
        return (int) ($this->field_two ?? 0);
    }

    /**
     * Get leave type status (from field_three)
     */
    public function getLeaveTypeStatusAttribute(): bool
    {
        return $this->field_three === '1' || $this->field_three === 'active';
    }

    /**
     * Create a leave type constant
     */
    public static function createLeaveType(int $companyId, string $name, ?string $shortName = null, int $days = 0): self
    {
        return self::create([
            'company_id' => $companyId,
            'type' => self::TYPE_LEAVE_TYPE,
            'category_name' => $name,
            'field_one' => $shortName,
            'field_two' => (string) $days,
            'field_three' => '1', // active
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all leave types for a company
     */
    public static function getLeaveTypesForCompany(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return self::forCompany($companyId)
            ->leaveTypes()
            ->where('field_three', '1') // active only
            ->orderBy('category_name')
            ->get();
    }

    /**
     * Get all active leave types (including general ones)
     */
    public static function getActiveLeaveTypes(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where(function ($query) use ($companyId) {
            $query->where('company_id', $companyId)
                ->orWhere('company_id', 0); // General types
        })
            ->leaveTypes()
            ->where('field_three', '1') // active only
            ->orderBy('category_name')
            ->get();
    }

    /**
     * Get all active leave types by company name
     */
    public static function getActiveLeaveTypesByCompanyName(string $companyName): \Illuminate\Database\Eloquent\Collection
    {
        // Get company IDs that match the company name
        $companyIds =  User::where('company_name', $companyName)
            ->distinct()
            ->pluck('company_id')
            ->toArray();

        // Include general types (company_id = 0)
        $companyIds[] = 0;

        return self::whereIn('company_id', $companyIds)
            ->leaveTypes()
            ->where('field_three', '1') // active only
            ->orderBy('category_name')
            ->get();
    }

    /**
     * Get religions list
     */
    public static function getReligions(int $companyId): array
    {
        return self::where('type', self::TYPE_RELIGION)
            ->where('company_id', $companyId)
            ->get()
            ->map(function ($constant) {
                return [
                    'id' => $constant->constants_id,
                    'religion' => $constant->category_name,
                ];
            })
            ->toArray();
    }
}
