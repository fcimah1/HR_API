<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrainingPerformanceEnum;
use App\Enums\TrainingStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Training Model
 * نموذج التدريب
 */
class Training extends Model
{
    protected $table = 'ci_training';
    protected $primaryKey = 'training_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'department_id',
        'employee_id',
        'training_type_id',
        'associated_goals',
        'trainer_id',
        'start_date',
        'finish_date',
        'training_cost',
        'training_status',
        'description',
        'performance',
        'remarks',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'department_id' => 'integer',
        'training_type_id' => 'integer',
        'trainer_id' => 'integer',
        'training_cost' => 'decimal:2',
        'training_status' => 'integer',
        'performance' => 'integer',
    ];

    // ==================== Relationships ====================

    /**
     * Get the trainer for this training
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'trainer_id', 'trainer_id');
    }

    /**
     * Get the training type (from erp_constants)
     */
    public function trainingType(): BelongsTo
    {
        return $this->belongsTo(ErpConstant::class, 'training_type_id', 'constants_id');
    }

    /**
     * Get the department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * Get training notes
     */
    public function notes(): HasMany
    {
        return $this->hasMany(TrainingNote::class, 'training_id', 'training_id');
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
     * Scope for filtering by status
     */
    public function scopeWithStatus($query, int $status)
    {
        return $query->where('training_status', $status);
    }

    /**
     * Scope for filtering by trainer
     */
    public function scopeByTrainer($query, int $trainerId)
    {
        return $query->where('trainer_id', $trainerId);
    }

    /**
     * Scope for filtering by department
     */
    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    // ==================== Accessors ====================

    /**
     * Get status enum
     */
    public function getStatusEnumAttribute(): ?TrainingStatusEnum
    {
        return TrainingStatusEnum::tryFrom($this->training_status);
    }

    /**
     * Get status label in Arabic
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status_enum?->label() ?? 'غير محدد';
    }

    /**
     * Get performance enum
     */
    public function getPerformanceEnumAttribute(): ?TrainingPerformanceEnum
    {
        $perf = is_numeric($this->performance) ? (int) $this->performance : null;
        return $perf !== null ? TrainingPerformanceEnum::tryFrom($perf) : null;
    }

    /**
     * Get performance label in Arabic
     */
    public function getPerformanceLabelAttribute(): string
    {
        return $this->performance_enum?->label() ?? 'غير محدد';
    }

    /**
     * Get employee IDs as array
     */
    public function getEmployeeIdsArrayAttribute(): array
    {
        if (empty($this->employee_id)) {
            return [];
        }

        return array_filter(
            array_map('intval', explode(',', $this->employee_id)),
            fn($id) => $id > 0
        );
    }

    /**
     * Get training type name
     */
    public function getTrainingTypeNameAttribute(): ?string
    {
        return $this->trainingType?->category_name;
    }

    /**
     * Get trainer full name
     */
    public function getTrainerNameAttribute(): ?string
    {
        if (!$this->trainer) {
            return null;
        }
        return trim($this->trainer->first_name . ' ' . $this->trainer->last_name);
    }

    /**
     * Get department name
     */
    public function getDepartmentNameAttribute(): ?string
    {
        return $this->department?->department_name;
    }

    // ==================== Methods ====================

    /**
     * Set employee IDs from array
     */
    public function setEmployeeIdsFromArray(array $employeeIds): void
    {
        $this->employee_id = implode(',', array_filter($employeeIds, fn($id) => $id > 0));
    }

    /**
     * Check if training is pending
     */
    public function isPending(): bool
    {
        return $this->training_status === TrainingStatusEnum::PENDING->value;
    }

    /**
     * Check if training has started
     */
    public function isStarted(): bool
    {
        return $this->training_status === TrainingStatusEnum::STARTED->value;
    }

    /**
     * Check if training is completed
     */
    public function isCompleted(): bool
    {
        return $this->training_status === TrainingStatusEnum::COMPLETED->value;
    }

    /**
     * Check if training is rejected
     */
    public function isRejected(): bool
    {
        return $this->training_status === TrainingStatusEnum::REJECTED->value;
    }
}
