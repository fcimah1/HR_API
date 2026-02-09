<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $table = 'ci_visitors';
    protected $primaryKey = 'visitor_id';

    public $timestamps = false; // يستخدم varchar للـ timestamps حسب الصورة

    protected $fillable = [
        'company_id',
        'department_id',
        'visit_purpose',
        'visitor_name',
        'phone',
        'email',
        'visit_date',
        'check_in',
        'check_out',
        'address',
        'description',
        'created_by',
        'created_at',
    ];

    /**
     * العلاقة مع القسم
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    /**
     * العلاقة مع منشئ السجل
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}
