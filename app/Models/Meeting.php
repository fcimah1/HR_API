<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $table = 'ci_meetings';
    protected $primaryKey = 'meeting_id';

    // نستخدم timestamps = false لأن الجدول يستخدم varchar للـ timestamps حسب الصورة
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'meeting_title',
        'meeting_date',
        'meeting_time',
        'meeting_room',
        'meeting_note',
        'meeting_color',
        'created_at',
    ];

    /**
     * الحصول على الموظفين المشاركين
     * الحقل يحتوي على معرفات مفصولة بفاصلة
     */
    public function getEmployeeIdsAttribute($value): array
    {
        $ids = $this->attributes['employee_id'] ?? '';
        return array_filter(explode(',', $ids), fn($id) => !empty($id));
    }
}
