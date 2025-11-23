<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;   // ← **Add this line**

class Travel extends Model
{
    use HasFactory;

    protected $table = 'ci_travels';
    protected $primaryKey = 'travel_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'start_date',
        'end_date',
        'associated_goals',
        'visit_purpose',
        'visit_place',
        'travel_mode', // 1: Bus, 2: Train, 3: Plane, 4: Taxi, 5: Rental Car
        'arrangement_type',
        'expected_budget',
        'actual_budget',
        'description',
        'status', // 0: Pending, 1: Accepted, 2: Rejected
        'added_by',
        'created_at'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'expected_budget' => 'decimal:2',
        'actual_budget' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'user_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by', 'user_id');
    }

    public function arrangementType()
    {
        return $this->belongsTo(ErpConstant::class, 'arrangement_type', 'constants_id')
            ->where('type', 'travel_type');
    }

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    const TRAVEL_MODE_BUS = 1;
    const TRAVEL_MODE_TRAIN = 2;
    const TRAVEL_MODE_PLANE = 3;
    const TRAVEL_MODE_TAXI = 4;
    const TRAVEL_MODE_RENTAL_CAR = 5;
}
