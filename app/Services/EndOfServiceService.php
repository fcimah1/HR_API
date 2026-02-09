<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\Interface\EndOfServiceRepositoryInterface;
use App\DTOs\EndOfService\EndOfServiceFilterDTO;
use App\DTOs\EndOfService\CreateEndOfServiceDTO;
use App\DTOs\EndOfService\UpdateEndOfServiceDTO;
use App\Models\EndOfService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EndOfServiceService
{
    public function __construct(
        private readonly EndOfServiceRepositoryInterface $endOfServiceRepository,
        private readonly \App\Repository\Interface\LeaveRepositoryInterface $leaveRepository
    ) {}

    /**
     * الحصول على جميع الحسابات
     */
    public function getAllCalculations(EndOfServiceFilterDTO $filters): mixed
    {
        return $this->endOfServiceRepository->getAll($filters);
    }

    /**
     * الحصول على حساب بالـ ID
     */
    public function getCalculationById(int $id, int $companyId): ?EndOfService
    {
        return $this->endOfServiceRepository->getById($id, $companyId);
    }

    /**
     * حساب مستحقات نهاية الخدمة (بدون حفظ - للمعاينة)
     */
    public function calculate(
        int $employeeId,
        string $terminationDate,
        string $terminationType,
        bool $includeLeave = true,
        int $companyId
    ): array {
        // جلب بيانات الموظف
        $employee = User::with('details')
            ->where('user_id', $employeeId)
            ->where('company_id', $companyId)
            ->first();

        if (!$employee) {
            throw new \Exception('الموظف غير موجود');
        }

        $details = $employee->details;
        if (!$details || !$details->date_of_joining) {
            throw new \Exception('بيانات التعيين غير موجودة للموظف');
        }

        // حساب مدة الخدمة
        $hireDate = Carbon::parse($details->date_of_joining);
        $endDate = Carbon::parse($terminationDate);
        $servicePeriod = $this->calculateServicePeriod($hireDate, $endDate);

        // الرواتب
        $basicSalary = (float)($details->basic_salary ?? 0);
        $allowances = (float)($details->allowance ?? 0);
        $totalSalary = $basicSalary + $allowances;

        // حساب مكافأة نهاية الخدمة
        $gratuityAmount = $this->calculateGratuity(
            $totalSalary,
            $servicePeriod['total_days'],
            $terminationType
        );

        // حساب تعويض الإجازات
        $unusedLeaveDays = 0;
        $leaveCompensation = 0;

        if ($includeLeave) {
            $unusedLeaveDays = $this->calculateUnusedLeave($employeeId, $servicePeriod, $employee->company_id, $hireDate, $endDate);
            $dailySalary = $totalSalary / 30;
            $leaveCompensation = $unusedLeaveDays * $dailySalary;
        }

        // الإجمالي
        $totalCompensation = $gratuityAmount + $leaveCompensation;

        return [
            'employee_id' => $employeeId,
            'employee_name' => $employee->first_name . ' ' . $employee->last_name,
            'hire_date' => $hireDate->format('Y-m-d'),
            'termination_date' => $terminationDate,
            'termination_type' => $terminationType,
            'service_years' => $servicePeriod['years'],
            'service_months' => $servicePeriod['months'],
            'service_days' => $servicePeriod['days'],
            'total_service_days' => $servicePeriod['total_days'],
            'basic_salary' => round($basicSalary, 2),
            'allowances' => round($allowances, 2),
            'total_salary' => round($totalSalary, 2),
            'gratuity_amount' => round($gratuityAmount, 2),
            'unused_leave_days' => $unusedLeaveDays,
            'leave_compensation' => round($leaveCompensation, 2),
            'notice_compensation' => 0, // يمكن إضافتها لاحقاً
            'total_compensation' => round($totalCompensation, 2),
            'calculation_details' => $this->getCalculationDetails(
                $totalSalary,
                $servicePeriod,
                $terminationType
            ),
        ];
    }

    /**
     * حساب وحفظ مستحقات نهاية الخدمة
     */
    public function calculateAndSave(
        int $employeeId,
        string $terminationDate,
        string $terminationType,
        bool $includeLeave,
        int $companyId,
        int $calculatedBy,
        ?string $notes = null
    ): EndOfService {
        // حساب المستحقات
        $calculation = $this->calculate(
            $employeeId,
            $terminationDate,
            $terminationType,
            $includeLeave,
            $companyId
        );

        // إنشاء DTO للحفظ
        $dto = new CreateEndOfServiceDTO(
            companyId: $companyId,
            employeeId: $employeeId,
            hireDate: $calculation['hire_date'],
            terminationDate: $terminationDate,
            terminationType: $terminationType,
            serviceYears: $calculation['service_years'],
            serviceMonths: $calculation['service_months'],
            serviceDays: $calculation['service_days'],
            basicSalary: $calculation['basic_salary'],
            allowances: $calculation['allowances'],
            totalSalary: $calculation['total_salary'],
            gratuityAmount: $calculation['gratuity_amount'],
            leaveCompensation: $calculation['leave_compensation'],
            noticeCompensation: $calculation['notice_compensation'] ?? 0,
            totalCompensation: $calculation['total_compensation'],
            unusedLeaveDays: $calculation['unused_leave_days'],
            calculatedBy: $calculatedBy,
            calculatedAt: now()->format('Y-m-d H:i:s'),
            notes: $notes
        );

        // التحقق من وجود حساب معلق
        $pendingCalculation = $this->endOfServiceRepository->findPendingByEmployeeId($employeeId, $companyId);

        if ($pendingCalculation) {
            return $this->endOfServiceRepository->updateCalculation($pendingCalculation, $dto);
        }

        return $this->endOfServiceRepository->create($dto);
    }

    /**
     * حساب أيام الإجازة غير المستخدمة مع مراعاة المنطق المعقد للرصيد
     */
    private function calculateUnusedLeave(int $employeeId, array $servicePeriod, int $companyId, Carbon $hireDate, Carbon $endDate): float
    {
        // 1. البحث عن نوع الإجازة المناسب (أولاً "المستحق"، ثم "السنوي")
        $leaveType = \App\Models\ErpConstant::where('type', 'leave_type')
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('category_name', 'LIKE', '%مستحق%')
                    ->orWhere('category_name', 'LIKE', '%entitled%')
                    ->orWhere('category_name', 'LIKE', '%Annual%')
                    ->orWhere('category_name', 'LIKE', '%سنوية%');
            })
            ->orderByRaw("CASE WHEN category_name LIKE '%مستحق%' THEN 1 ELSE 2 END")
            ->first();

        // Fallback
        if (!$leaveType) {
            $leaveType = \App\Models\ErpConstant::where('type', 'leave_type')
                ->where('company_id', $companyId)
                ->where('field_one', 'LIKE', '%quota_assign%')
                ->first();
        }

        $leaveTypeId = $leaveType ? $leaveType->constants_id : 1;

        // 2. حساب رصيد الإجازة بناءً على "السنوات الميلادية" (Calendar Years)
        // القاعدة المستنبطة من النظام القديم: كل جزء من الشهر يحسب كشهر كامل
        $totalEntitledHours = 0;

        // جلب معلومات التوظيف والإنهاء من السيرفس بيريود أو الاستعلام
        // سنحتاج لتواريخ دقيقة هنا، لذا سنعيد جلب الموظف أو نعتمد على المتغيرات الممررة
        // ولكن المتغيرات الممررة هي مصفوفة، لذا نحتاج التواريخ الخام لحساب السنوات الميلادية.
        // بما أن الدالة خاصة، يمكننا تغيير المعاملات أو جلب الموظف. الأسهل جلب الموظف لضمان التواريخ.
        // $employee = \App\Models\User::find($employeeId); // Already fetched in calculate method
        // $hireDate = \Carbon\Carbon::parse($employee->joining_date); // Passed as argument
        // تاريخ الإنهاء هو اليوم (أو التاريخ الممرر في الطلب، لكن هنا سنفترض أنه اليوم للحساب أو نمرره)
        // ملاحظة: logic EndOfServiceService::calculate يمرر التواريخ.
        // سنقوم بتعديل بسيط لاستخدام تواريخ فترة الخدمة بشكل أدق.

        // للتأكد من تطابق التواريخ تماماً مع المدخلات (التي قد تكون termination date مختلف عن اليوم)
        // سنعتمد على الحساب التراكمي للسنوات الميلادية بين سنة التعيين وسنة النهاية المتوقعة
        $startYear = $hireDate->year;
        $endYear = $endDate->year;

        if ($leaveType && !empty($leaveType->field_one)) {
            $leaveConfig = @unserialize($leaveType->field_one);
            $quotas = ($leaveConfig && isset($leaveConfig['quota_assign']) && is_array($leaveConfig['quota_assign']))
                ? $leaveConfig['quota_assign']
                : [];
        } else {
            $quotas = [];
        }

        for ($year = $startYear; $year <= $endYear; $year++) {
            // تحديد بداية ونهاية الفترة المحتسبة لهذا العام
            $yearStart = \Carbon\Carbon::create($year, 1, 1);
            $yearEnd = \Carbon\Carbon::create($year, 12, 31);

            // تقاطع فترة العمل مع السنة الحالية
            $effectiveStart = $year == $startYear ? $hireDate : $yearStart;
            $effectiveEnd = $year == $endYear ? $endDate : $yearEnd;

            if ($effectiveStart > $effectiveEnd) continue;

            // حساب عدد الأشهر (جزء من الشهر = شهر كامل)
            // الفرق بالأشهر + 1 (لأن العد inclusive)
            // مثال: 21 يونيو إلى 31 ديسمبر
            // 12 - 6 = 6. لكن يونيو محسوب، وديسمبر محسوب.
            // يونيو، يوليو، اغسطس، سبتمبر، اكتوبر، نوفمبر، ديسمبر = 7 أشهر
            // المعادلة: (MonthEnd - MonthStart) + 1
            $monthsInYear = ($effectiveEnd->month - $effectiveStart->month) + 1;

            // تحديد الرصيد السنوي (بناءً على سنة الخدمة)
            $serviceYearIndex = $year - $startYear; // 0, 1, 2...

            // محاولة جلب الرصيد من الإعدادات
            $yearQuotaHours = 0;
            // البحث عن أقرب مفتاح
            $quotaKey = isset($quotas[$serviceYearIndex + 1]) ? ($serviceYearIndex + 1) : (isset($quotas[$serviceYearIndex]) ? $serviceYearIndex : 0);
            // لاحظ أن مصفوفة CodeIgniter تبدأ غالباً من 1 أو 0، حسب الددمب السابق (1=>105, 2=>105)
            // الددمب أظهر: i:1;s:3:"105"; i:2...
            // إذن الفهرس 1 هو السنة الأولى. إذن نستخدم $serviceYearIndex + 1

            if (isset($quotas[$serviceYearIndex + 1])) {
                $yearQuotaHours = (float)$quotas[$serviceYearIndex + 1];
            } elseif (count($quotas) > 0) {
                // إذا تجاوزنا السنوات المعرفة، نأخذ آخر قيمة
                $yearQuotaHours = (float)end($quotas);
            }

            // Fallback: إذا الرصيد 0، نستخدم fallback heuristic (مثل 168 أو 105)
            // بناء على الصورة، المستخدم لديه 168
            if ($yearQuotaHours == 0) $yearQuotaHours = 168; // Default to 21 days if config missing

            // حساب المستحق لهذا العام بالساعات
            // المعادلة: (عدد الأشهر / 12) * رصيد السنة
            $entitledHours = ($monthsInYear / 12) * $yearQuotaHours;

            $totalEntitledHours += $entitledHours;
        }

        // 3. حساب المسحوبات
        $usedHours = $this->leaveRepository->getTotalUsedLeave($employeeId, $leaveTypeId, $companyId);

        // 4. الصافي بالأيام
        $netHours = max(0, $totalEntitledHours - $usedHours);
        return $netHours / 8;
    }

    /**
     * تحديث حساب
     */
    public function updateCalculation(int $id, int $companyId, UpdateEndOfServiceDTO $dto): ?EndOfService
    {
        $calculation = $this->endOfServiceRepository->getById($id, $companyId);
        if (!$calculation) {
            return null;
        }

        return $this->endOfServiceRepository->update($calculation, $dto);
    }

    /**
     * حذف حساب
     */
    public function deleteCalculation(int $id, int $companyId): bool
    {
        return $this->endOfServiceRepository->delete($id, $companyId);
    }

    /**
     * حساب مدة الخدمة
     */
    private function calculateServicePeriod(Carbon $hireDate, Carbon $terminationDate): array
    {
        $interval = $hireDate->diff($terminationDate);

        return [
            'years' => $interval->y,
            'months' => $interval->m,
            'days' => $interval->d,
            'total_days' => $interval->days,
        ];
    }

    /**
     * حساب مكافأة نهاية الخدمة وفق نظام العمل السعودي
     */
    private function calculateGratuity(
        float $totalSalary,
        int $totalDays,
        string $terminationType
    ): float {
        // تحويل المدة لسنوات عشرية (بناءً على إجمالي الأيام للحصول على أدق نتيجة)
        $totalYears = $totalDays / 365;

        if ($totalYears < 1) {
            return 0; // لا مكافأة إذا أقل من سنة
        }

        $halfMonthSalary = $totalSalary / 2;
        $gratuity = 0;

        // للاستقالة
        if ($terminationType === 'resignation') {
            if ($totalYears < 2) {
                // أقل من سنتين: لا مكافأة
                return 0;
            } elseif ($totalYears >= 2 && $totalYears < 5) {
                // من 2 إلى أقل من 5 سنوات: ثلث المكافأة
                $gratuity = $this->calculateFullGratuity($totalSalary, $totalYears) / 3;
            } elseif ($totalYears >= 5 && $totalYears < 10) {
                // من 5 إلى أقل من 10 سنوات: ثلثي المكافأة
                $gratuity = $this->calculateFullGratuity($totalSalary, $totalYears) * (2 / 3);
            } else {
                // 10 سنوات فأكثر: المكافأة كاملة
                $gratuity = $this->calculateFullGratuity($totalSalary, $totalYears);
            }
        } else {
            // لإنهاء الخدمة أو انتهاء العقد: المكافأة كاملة
            $gratuity = $this->calculateFullGratuity($totalSalary, $totalYears);
        }

        return $gratuity;
    }

    /**
     * حساب المكافأة الكاملة (بدون تخفيض الاستقالة)
     */
    private function calculateFullGratuity(float $totalSalary, float $totalYears): float
    {
        $halfMonthSalary = $totalSalary / 2;
        $fullMonthSalary = $totalSalary;
        $gratuity = 0;

        if ($totalYears <= 5) {
            // أول 5 سنوات: نصف راتب لكل سنة
            $gratuity = $halfMonthSalary * $totalYears;
        } else {
            // أول 5 سنوات: نصف راتب
            $first5Years = $halfMonthSalary * 5;
            // ما بعد 5 سنوات: راتب كامل
            $remainingYears = $totalYears - 5;
            $remaining = $fullMonthSalary * $remainingYears;
            $gratuity = $first5Years + $remaining;
        }

        // الحد الأقصى: راتب سنتين (24 شهر)
        $maxGratuity = $totalSalary * 24;
        return min($gratuity, $maxGratuity);
    }


    /**
     * الحصول على تفاصيل الحساب للعرض
     */
    private function getCalculationDetails(float $totalSalary, array $servicePeriod, string $terminationType): array
    {
        $totalYears = $servicePeriod['total_days'] / 365;

        $details = [
            'total_salary' => $totalSalary,
            'half_month_salary' => $totalSalary / 2,
            'daily_salary' => $totalSalary / 30,
            'total_years_decimal' => round($totalYears, 2),
            'termination_type' => $terminationType,
        ];

        if ($terminationType === 'resignation') {
            if ($totalYears < 2) {
                $details['gratuity_percentage'] = '0%';
                $details['gratuity_reason'] = 'استقالة أقل من سنتين';
            } elseif ($totalYears < 5) {
                $details['gratuity_percentage'] = '33.33%';
                $details['gratuity_reason'] = 'استقالة من 2 إلى أقل من 5 سنوات';
            } elseif ($totalYears < 10) {
                $details['gratuity_percentage'] = '66.67%';
                $details['gratuity_reason'] = 'استقالة من 5 إلى أقل من 10 سنوات';
            } else {
                $details['gratuity_percentage'] = '100%';
                $details['gratuity_reason'] = 'استقالة 10 سنوات فأكثر';
            }
        } else {
            $details['gratuity_percentage'] = '100%';
            $details['gratuity_reason'] = 'إنهاء خدمة أو انتهاء عقد';
        }

        return $details;
    }
}
