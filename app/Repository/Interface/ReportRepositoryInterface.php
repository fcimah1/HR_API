<?php

declare(strict_types=1);

namespace App\Repository\Interface;

use App\DTOs\Report\AttendanceReportFilterDTO;
use Illuminate\Support\Collection;

/**
 * واجهة Repository للتقارير
 * Report Repository Interface
 */
interface ReportRepositoryInterface
{
    // ==========================================
    // تقارير الحضور والانصراف (Attendance Reports)
    // ==========================================

    /**
     * تقرير الحضور الشهري
     * 
     * @param AttendanceReportFilterDTO $filters
     * @return Collection
     */
    public function getAttendanceMonthlyReport(AttendanceReportFilterDTO $filters): Collection;

    /**
     * تقرير أول وآخر حضور/انصراف
     * 
     * @param AttendanceReportFilterDTO $filters
     * @return Collection
     */
    public function getAttendanceFirstLastReport(AttendanceReportFilterDTO $filters): Collection;

    /**
     * تقرير سجلات الوقت
     * 
     * @param AttendanceReportFilterDTO $filters
     * @return Collection
     */
    public function getAttendanceTimeRecordsReport(AttendanceReportFilterDTO $filters): Collection;

    /**
     * تقرير الحضور بنطاق زمني
     * 
     * @param AttendanceReportFilterDTO $filters
     * @return Collection
     */
    public function getAttendanceDateRangeReport(AttendanceReportFilterDTO $filters): Collection;

    /**
     * تقرير سجل الدوام (Timesheet)
     * 
     * @param AttendanceReportFilterDTO $filters
     * @return Collection
     */
    public function getTimesheetReport(AttendanceReportFilterDTO $filters): Collection;

    // ==========================================
    // التقارير المالية (Financial Reports)
    // ==========================================

    /**
     * تقرير السلف والقروض
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getLoanReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير الرواتب
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getPayrollReport(int $companyId, array $filters = []): Collection;

    /**
     * جلب أنواع البدلات للشركة
     * 
     * @param int $companyId
     * @return Collection
     */
    public function getAllowanceTypes(int $companyId): Collection;

    /**
     * جلب أنواع الخصومات النظامية للشركة
     * 
     * @param int $companyId
     * @return Collection
     */
    public function getStatutoryTypes(int $companyId): Collection;

    // ==========================================
    // تقارير الموارد البشرية (HR Reports)
    // ==========================================

    /**
     * تقرير الإجازات
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getLeaveReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير المكافآت - Placeholder
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getAwardsReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير الترقيات - Placeholder
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getPromotionsReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير الاستقالات
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getResignationsReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير إنهاء الخدمة - Placeholder
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getTerminationsReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير التحويلات
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getTransfersReport(int $companyId, array $filters = []): Collection;

    // ==========================================
    // تقارير الوثائق (Document Reports)
    // ==========================================

    /**
     * تقرير تجديد الإقامة - Placeholder
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getResidenceRenewalReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير العقود قريبة الانتهاء - Placeholder
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getExpiringContractsReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير الهويات/الإقامات قريبة الانتهاء - Placeholder
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getExpiringDocumentsReport(int $companyId, array $filters = []): Collection;

    // ==========================================
    // تقارير الموظفين (Employee Reports)
    // ==========================================

    /**
     * تقرير الموظفين حسب الفرع
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getEmployeesByBranchReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير الموظفين حسب الدولة
     * 
     * @param int $companyId
     * @param array $filters
     * @return Collection
     */
    public function getEmployeesByCountryReport(int $companyId, array $filters = []): Collection;

    /**
     * تقرير حسابات نهاية الخدمة - Placeholder
     * 
     * @return Collection
     */
    public function getEndOfServiceReport(array $filters = []): Collection;

}
