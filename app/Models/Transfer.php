<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ci_transfers';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'transfer_id';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    /**
     * Approval status constants
     */
    const APPROVAL_PENDING = 0;
    const APPROVAL_APPROVED = 1;
    const APPROVAL_REJECTED = 2;

    /**
     * Transfer type constants
     */
    const TYPE_INTERNAL = 'internal';
    const TYPE_BRANCH = 'branch';
    const TYPE_INTERCOMPANY = 'intercompany';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'employee_id',
        'old_salary',
        'old_designation',
        'old_department',
        'transfer_date',
        'transfer_department',
        'transfer_designation',
        'new_salary',
        'old_company_id',
        'old_branch_id',
        'new_company_id',
        'new_branch_id',
        'old_currency',
        'new_currency',
        'reason',
        'status',
        'current_company_approval',
        'new_company_approval',
        'transfer_type',
        'added_by',
        'notify_send_to',
        'created_at',
        'custody_clearance_notes',
        'blocked_reasons',
        'executed_at',
        'executed_by',
        'validation_notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'old_salary' => 'integer',
        'old_designation' => 'integer',
        'old_department' => 'integer',
        'transfer_department' => 'integer',
        'transfer_designation' => 'integer',
        'new_salary' => 'integer',
        'old_company_id' => 'integer',
        'old_branch_id' => 'integer',
        'new_company_id' => 'integer',
        'new_branch_id' => 'integer',
        'old_currency' => 'integer',
        'new_currency' => 'integer',
        'added_by' => 'integer',
        'status' => 'integer',
        'current_company_approval' => 'integer',
        'new_company_approval' => 'integer',
        'blocked_reasons' => 'array',
        'executed_at' => 'datetime',
        'executed_by' => 'integer',
    ];

    /**
     * Get the employee being transferred.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Get the user who added the transfer.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by', 'user_id');
    }

    /**
     * Get the old department.
     */
    public function oldDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'old_department', 'department_id');
    }

    /**
     * Get the new department.
     */
    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'transfer_department', 'department_id');
    }

    /**
     * Get the old designation.
     */
    public function oldDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'old_designation', 'designation_id');
    }

    /**
     * Get the new designation.
     */
    public function newDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'transfer_designation', 'designation_id');
    }

    /**
     * Get the old company.
     */
    public function oldCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_company_id', 'user_id')
            ->where('user_type', 'company');
    }

    /**
     * Get the new company.
     */
    public function newCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_company_id', 'user_id')
            ->where('user_type', 'company');
    }

    /**
     * Get status text in Arabic.
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'قيد المراجعة',
            self::STATUS_APPROVED => 'موافق عليه',
            self::STATUS_REJECTED => 'مرفوض',
            default => 'غير محدد',
        };
    }

    /**
     * Get status text in English.
     */
    public function getStatusTextEnAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    /**
     * Get transfer type text in Arabic.
     */
    public function getTransferTypeTextAttribute(): string
    {
        return match ($this->transfer_type) {
            self::TYPE_INTERNAL => 'نقل داخلي',
            self::TYPE_BRANCH => 'نقل بين الفروع',
            self::TYPE_INTERCOMPANY => 'نقل بين الشركات',
            default => 'غير محدد',
        };
    }

    /**
     * Get transfer type text in English.
     */
    public function getTransferTypeTextEnAttribute(): string
    {
        return match ($this->transfer_type) {
            self::TYPE_INTERNAL => 'Internal Transfer',
            self::TYPE_BRANCH => 'Branch Transfer',
            self::TYPE_INTERCOMPANY => 'Intercompany Transfer',
            default => 'Unknown',
        };
    }

    /**
     * Get current company approval text in Arabic.
     */
    public function getCurrentCompanyApprovalTextAttribute(): ?string
    {
        if ($this->current_company_approval === null) {
            return null;
        }

        return match ($this->current_company_approval) {
            self::APPROVAL_PENDING => 'قيد الانتظار',
            self::APPROVAL_APPROVED => 'موافق',
            self::APPROVAL_REJECTED => 'مرفوض',
            default => 'غير محدد',
        };
    }

    /**
     * Get new company approval text in Arabic.
     */
    public function getNewCompanyApprovalTextAttribute(): ?string
    {
        if ($this->new_company_approval === null) {
            return null;
        }

        return match ($this->new_company_approval) {
            self::APPROVAL_PENDING => 'قيد الانتظار',
            self::APPROVAL_APPROVED => 'موافق',
            self::APPROVAL_REJECTED => 'مرفوض',
            default => 'غير محدد',
        };
    }

    /**
     * Check if this is an internal transfer.
     */
    public function isInternalTransfer(): bool
    {
        return $this->transfer_type === self::TYPE_INTERNAL;
    }

    /**
     * Check if this is a branch transfer.
     */
    public function isBranchTransfer(): bool
    {
        return $this->transfer_type === self::TYPE_BRANCH;
    }

    /**
     * Check if this is an intercompany transfer.
     */
    public function isIntercompanyTransfer(): bool
    {
        return $this->transfer_type === self::TYPE_INTERCOMPANY;
    }
    /**
     * Get the approvals for this transfer.
     */
    public function approvals()
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'transfer_id')
            ->where('module_option', 'transfer_settings');
    }
}
