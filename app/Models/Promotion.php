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
}
