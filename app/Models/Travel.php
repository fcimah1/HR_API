<?php

namespace App\Models;

use App\Enums\TravelModeEnum;
use App\Enums\NumericalStatusEnum;
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

    /**
     * Get the approvals for this travel request
     */
    public function approvals()
    {
        return $this->hasMany(StaffApproval::class, 'module_key_id', 'travel_id')
            ->where('module_option', 'travel_request_settings');
    }

    const STATUS_PENDING = NumericalStatusEnum::PENDING->value;
    const STATUS_APPROVED = NumericalStatusEnum::APPROVED->value;
    const STATUS_REJECTED = NumericalStatusEnum::REJECTED->value;

    const TRAVEL_MODE_BUS = TravelModeEnum::BUS->value;
    const TRAVEL_MODE_TRAIN = TravelModeEnum::TRAIN->value;
    const TRAVEL_MODE_PLANE = TravelModeEnum::PLANE->value;
    const TRAVEL_MODE_TAXI = TravelModeEnum::TAXI->value;
    const TRAVEL_MODE_RENTAL_CAR = TravelModeEnum::RENTAL_CAR->value;
}
