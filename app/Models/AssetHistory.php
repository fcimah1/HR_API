<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetHistory extends Model
{
    use HasFactory;

    protected $table = 'ci_asset_history';
    protected $primaryKey = 'id';
    public $timestamps = false;

    /**
     * Action constants
     */
    const ACTION_ASSIGNED = 'assigned';
    const ACTION_UNASSIGNED = 'unassigned';
    const ACTION_UPDATED = 'updated';
    const ACTION_REPORTED = 'reported';
    const ACTION_STATUS_CHANGED = 'status_changed';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'asset_id',
        'company_id',
        'employee_id',
        'action',
        'changed_by',
        'old_value',
        'new_value',
        'notes',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'id' => 'integer',
        'asset_id' => 'integer',
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'changed_by' => 'integer',
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    /**
     * Get the asset
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id', 'assets_id');
    }

    /**
     * Get the user who made the change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'user_id');
    }

    /**
     * Get the employee associated with this history entry
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Scope to filter by asset
     */
    public function scopeForAsset($query, int $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    /**
     * Scope to filter by company
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}

