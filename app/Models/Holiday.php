<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Holiday extends Model
{
    protected $table = 'ci_holidays';
    protected $primaryKey = 'holiday_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'event_name',
        'start_date',
        'end_date',
        'description',
        'is_publish',
        'created_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_publish' => 'integer',
    ];

    /**
     * Relationship: Company
     */
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id', 'user_id');
    }

    /**
     * Scope: Filter by company
     */
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: Published holidays only
     */
    public function scopePublished($query)
    {
        return $query->where('is_publish', 1);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        });
    }

    /**
     * Check if date falls within holiday
     */
    public function includesDate(string $date): bool
    {
        $checkDate = Carbon::parse($date);
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        return $checkDate->between($start, $end, true);
    }

    /**
     * Get duration in days
     */
    public function getDurationDays(): int
    {
        return Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1;
    }
}
