<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PunchTypeEnum;
use App\Enums\VerifyModeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نموذج سجلات البصمة الخام
 * 
 * يخزن البيانات القادمة من أجهزة البصمة قبل معالجتها
 * 
 * @property int $id
 * @property int $company_id
 * @property int|null $branch_id
 * @property string $kiosk_code
 * @property int|null $user_id
 * @property \Carbon\Carbon $punch_time
 * @property int|null $punch_type
 * @property int|null $verify_mode
 * @property array|null $raw_data
 * @property bool $is_processed
 * @property \Carbon\Carbon|null $processed_at
 * @property int|null $attendance_id
 * @property string|null $processing_notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BiometricLog extends Model
{
    /**
     * اسم الجدول
     */
    protected $table = 'ci_biometric_logs';

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'kiosk_code',
        'user_id',
        'punch_time',
        'punch_type',
        'verify_mode',
        'raw_data',
        'is_processed',
        'processed_at',
        'attendance_id',
        'processing_notes',
    ];

    /**
     * تحويل الأنواع
     */
    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'user_id' => 'integer',
        'punch_time' => 'datetime',
        'punch_type' => 'integer',
        'verify_mode' => 'integer',
        'raw_data' => 'array',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
        'attendance_id' => 'integer',
    ];

    // ==========================================
    // العلاقات (Relationships)
    // ==========================================

    /**
     * العلاقة مع المستخدم
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * العلاقة مع تفاصيل الموظف
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(UserDetails::class, 'user_id', 'user_id');
    }

    /**
     * العلاقة مع الفرع
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    /**
     * العلاقة مع سجل الحضور
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id', 'id');
    }

    // ==========================================
    // النطاقات (Scopes)
    // ==========================================

    /**
     * نطاق للسجلات غير المعالجة
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * نطاق للسجلات المعالجة
     */
    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    /**
     * نطاق حسب الشركة
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * نطاق حسب الفرع
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * نطاق حسب كود الكشك في الجهاز
     */
    public function scopeForKioskCode($query, string $kioskCode)
    {
        return $query->where('kiosk_code', $kioskCode);
    }

    /**
     * نطاق حسب تاريخ البصمة
     */
    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('punch_time', $date);
    }

    /**
     * نطاق حسب نطاق زمني
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('punch_time', [$startDate, $endDate]);
    }

    // ==========================================
    // الدوال المساعدة (Helper Methods)
    // ==========================================

    /**
     * تحديد السجل كمعالج
     */
    public function markAsProcessed(?int $attendanceId = null, ?string $notes = null): bool
    {
        return $this->update([
            'is_processed' => true,
            'processed_at' => now(),
            'attendance_id' => $attendanceId,
            'processing_notes' => $notes,
        ]);
    }

    /**
     * الحصول على تسمية نوع البصمة
     */
    public function getPunchTypeLabelAttribute(): string
    {
        return PunchTypeEnum::from($this->punch_type)->labelAr();
    }

    /**
     * الحصول على تسمية طريقة التحقق
     */
    public function getVerifyModeLabelAttribute(): string
    {
        return VerifyModeEnum::from($this->verify_mode)->labelAr();
    }
}
