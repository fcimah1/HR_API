<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $clearance_id
 * @property int $company_id
 * @property int $employee_id
 * @property string $clearance_date
 * @property string|null $clearance_type
 * @property string|null $notes
 * @property string $status
 * @property int|null $approved_by
 * @property string|null $approved_date
 * @property int $created_by
 * @property string $created_at
 * @property string|null $updated_at
 */
class CustodyClearance extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_custody_clearance';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'clearance_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'clearance_date',
        'clearance_type',
        'notes',
        'status',
        'approved_by',
        'approved_date',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'clearance_date' => 'date',
        'approved_by' => 'integer',
        'approved_date' => 'datetime',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee who is being cleared.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the user who approved the clearance.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    /**
     * Get the user who created the clearance.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Get the clearance items.
     */
    public function items()
    {
        return $this->hasMany(CustodyClearanceItem::class, 'clearance_id', 'clearance_id');
    }

    /**
     * Get the approvals for this clearance.
     */
    public function approvals()
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'clearance_id')
            ->where('module_option', 'custody_clearance');
    }

    /**
     * Scope for pending clearances.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved clearances.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected clearances.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
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
}
