<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $table = 'ci_promotions';

    protected $primaryKey = 'promotion_id';

    protected $fillable = [
        'company_id',
        'employee_id',
        'promotion_title',
        'promotion_date',
        'description',
        'old_designation_id',
        'new_designation_id',
        'old_department_id',
        'new_department_id',
        'old_salary',
        'new_salary',
        'status', // 0: Pending, 1: Accepted, 2: Rejected
        'notify_send_to',
        'added_by',
        'created_at',
    ];

    protected $casts = [
        'old_salary' => 'decimal:2',
        'new_salary' => 'decimal:2',
        'status' => 'integer',
        'promotion_date' => 'date',
    ];

    public $timestamps = false;

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    public function oldDepartment()
    {
        return $this->belongsTo(Department::class, 'old_department_id', 'department_id');
    }

    public function newDepartment()
    {
        return $this->belongsTo(Department::class, 'new_department_id', 'department_id');
    }

    public function oldDesignation()
    {
        return $this->belongsTo(Designation::class, 'old_designation_id', 'designation_id');
    }

    public function newDesignation()
    {
        return $this->belongsTo(Designation::class, 'new_designation_id', 'designation_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Set notify_send_to as comma separated string.
     */
    public function setNotifySendToAttribute($value)
    {
        if (is_array($value)) {
            // Filter out zeros, empty values, and duplicates
            $filtered = array_unique(array_filter($value, function ($item) {
                return !empty($item) && is_numeric($item) && (int)$item > 0;
            }));
            $this->attributes['notify_send_to'] = implode(',', $filtered);
        } else {
            $this->attributes['notify_send_to'] = $value;
        }
    }

    /**
     * Get notify_send_to as array.
     */
    public function getNotifySendToAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // Handle old JSON format records
        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values(array_filter(array_map('intval', $decoded))) : [];
        }

        // Handle comma separated strings
        $parts = explode(',', $value);

        // Filter out empty strings and non-numeric/zero values
        $filtered = array_filter($parts, function ($part) {
            $trimmed = trim($part);
            return strlen($trimmed) > 0 && is_numeric($trimmed) && (int)$trimmed > 0;
        });

        return array_values(array_map('intval', $filtered));
    }
}
