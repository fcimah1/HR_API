<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Training Note Model
 * نموذج ملاحظات التدريب
 */
class TrainingNote extends Model
{
    protected $table = 'ci_training_notes';
    protected $primaryKey = 'training_note_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'training_id',
        'employee_id',
        'training_note',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'training_id' => 'integer',
        'employee_id' => 'integer',
        'training_note_id' => 'integer',
    ];

    // ==================== Relationships ====================

    /**
     * Get the training this note belongs to
     */
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class, 'training_id', 'training_id');
    }

    /**
     * Get the employee who created the note
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    // ==================== Scopes ====================

    /**
     * Scope for company isolation
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope for filtering by training
     */
    public function scopeForTraining($query, int $trainingId)
    {
        return $query->where('training_id', $trainingId);
    }

    // ==================== Accessors ====================

    /**
     * Get employee name
     */
    public function getEmployeeNameAttribute(): ?string
    {
        return $this->employee?->full_name;
    }
}
