<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPaymentHistory extends Model
{
    use HasFactory;

    protected $table = 'ci_loan_payment_history';
    protected $primaryKey = 'payment_id';
    public $timestamps = false;

    protected $fillable = [
        'advance_salary_id',
        'employee_id',
        'company_id',
        'amount_due',
        'amount_paid',
        'due_date',
        'paid_date',
        'is_late',
        'created_at',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
        'is_late' => 'boolean',
    ];

    /**
     * Get the advance salary request
     */
    public function advanceSalary(): BelongsTo
    {
        return $this->belongsTo(AdvanceSalary::class, 'advance_salary_id', 'advance_salary_id');
    }

    /**
     * Get the employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    /**
     * Check if payment is late
     */
    public function checkIfLate(): bool
    {
        if ($this->paid_date === null && now()->greaterThan($this->due_date)) {
            return true;
        }

        if ($this->paid_date !== null && \Carbon\Carbon::parse($this->paid_date)->greaterThan($this->due_date)) {
            return true;
        }

        return false;
    }

    /**
     * Scope: Late payments only
     */
    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    /**
     * Scope: By employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Check if employee has late payment history
     */
    public static function hasLatePaymentHistory(int $employeeId): bool
    {
        return self::where('employee_id', $employeeId)
            ->where('is_late', true)
            ->exists();
    }
}
