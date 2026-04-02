# خطة تنفيذ Module التقارير (Reports Module)

## وصف المشروع

إنشاء module جديد للتقارير في مشروع HR_API باستخدام مكتبة TCPDF. سيتم اتباع نفس النمط المعماري المستخدم في المشروع:

-   **DTOs (Data Transfer Objects)** للبيانات
-   **Request Validation** للتحقق من المدخلات
-   **Repository Pattern** للوصول للبيانات
-   **Services** لمنطق الأعمال
-   **Blade Views** لقوالب التقارير (PDF)

> **ملاحظات مهمة:**
>
> -   ✅ استخدام **Blade Views** لإنشاء قوالب التقارير
> -   ✅ صلاحية **موحدة**: `system_reports`
> -   ✅ **PDF فقط** (بدون CSV)
> -   ⚠️ التقارير التي لم يُنشأ لها module ستكون **placeholder** حتى اكتمال المشروع

---

## التقارير المطلوبة (20 تقرير)

| #   | التقرير                         | الوصف                            | Module موجود                 |
| --- | ------------------------------- | -------------------------------- | ---------------------------- |
| 1   | الحضور والانصراف (الشهر)        | تقرير الحضور لشهر محدد فقط       | ✅ Attendance                |
| 2   | الحضور والانصراف الأول والأخير  | أول وآخر تسجيل حضور/انصراف       | ✅ Attendance                |
| 3   | سجلات الوقت                     | Time logs للموظفين               | ✅ Attendance                |
| 4   | الحضور والانصراف (نطاق زمني)    | تقرير حسب فترة محددة             | ✅ Attendance                |
| 5   | سجل الدوام (Timesheet)          | تقرير الدوام الشامل              | ✅ Attendance (ci_timesheet) |
| 6   | الرواتب                         | تقرير الرواتب الشهرية            | ⚠️ Placeholder               |
| 7   | سلف الموظفين                    | السلف والقروض                    | ✅ AdvanceSalary             |
| 8   | الإجازات                        | تقرير الإجازات                   | ✅ Leave                     |
| 9   | المكافآت                        | تقرير المكافآت والجوائز          | ⚠️ Placeholder               |
| 10  | الترقيات                        | تقرير الترقيات                   | ⚠️ Placeholder               |
| 11  | الاستقالات                      | تقرير الاستقالات                 | ✅ Resignation               |
| 12  | إنهاء الخدمة                    | تقرير إنهاء الخدمة (Termination) | ⚠️ Placeholder               |
| 13  | التحويلات                       | تقرير النقل والتحويلات           | ✅ Transfer                  |
| 14  | تجديد الإقامة                   | تقرير تجديد الإقامات             | ⚠️ Placeholder               |
| 15  | العقود قريبة الانتهاء           | عقود تنتهي قريباً                | ⚠️ Placeholder               |
| 16  | الهويات/الإقامات قريبة الانتهاء | وثائق تنتهي قريباً               | ⚠️ Placeholder               |
| 17  | بيانات الموظفين حسب الفرع       | تقرير الموظفين per branch        | ✅ Employee                  |
| 18  | بيانات الموظفين حسب الدولة      | تقرير الموظفين per country       | ✅ Employee                  |
| 19  | حسابات نهاية الخدمة             | تقرير مستحقات نهاية الخدمة       | ⚠️ Placeholder               |
| 20  | تقرير الحسابات/المعاملات        | كشف حساب (Account Statement)     | ⚠️ Placeholder               |

### ملخص:

-   ✅ **مكتمل (9 تقارير)**: Attendance (4), Timesheet (1), AdvanceSalary (1), Leave (1), Resignation (1), Transfer (1), Employee (2)
-   ⚠️ **Placeholder (11 تقرير)**: Payroll, Awards, Promotions, Termination, Residence, Contracts, Documents, End of Service, Account Statement

---

## الهيكلة المقترحة

```
app/
├── DTOs/
│   └── Report/
│       ├── AttendanceReportFilterDTO.php
│       ├── PayrollReportFilterDTO.php
│       ├── LeaveReportFilterDTO.php
│       ├── LoanReportFilterDTO.php
│       ├── AwardReportFilterDTO.php
│       ├── PromotionReportFilterDTO.php
│       ├── ResignationReportFilterDTO.php
│       ├── TerminationReportFilterDTO.php
│       ├── TransferReportFilterDTO.php
│       ├── ResidenceRenewalReportFilterDTO.php
│       ├── ExpiringContractsReportFilterDTO.php
│       ├── ExpiringDocumentsReportFilterDTO.php
│       ├── EmployeesByBranchReportFilterDTO.php
│       ├── EmployeesByCountryReportFilterDTO.php
│       └── EndOfServiceReportFilterDTO.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── ReportController.php
│   │
│   └── Requests/
│       └── Report/
│           ├── AttendanceReportRequest.php
│           ├── PayrollReportRequest.php
│           ├── LeaveReportRequest.php
│           ├── LoanReportRequest.php
│           ├── AwardReportRequest.php
│           ├── PromotionReportRequest.php
│           ├── ResignationReportRequest.php
│           ├── TerminationReportRequest.php
│           ├── TransferReportRequest.php
│           ├── ResidenceRenewalReportRequest.php
│           ├── ExpiringContractsReportRequest.php
│           ├── ExpiringDocumentsReportRequest.php
│           ├── EmployeesByBranchReportRequest.php
│           ├── EmployeesByCountryReportRequest.php
│           └── EndOfServiceReportRequest.php
│
├── Repository/
│   ├── Interface/
│   │   └── ReportRepositoryInterface.php
│   └── ReportRepository.php
│
├── Services/
│   ├── ReportService.php
│   └── PdfGeneratorService.php
│
resources/
└── views/
    └── reports/
        ├── layouts/
        │   └── pdf-layout.blade.php          (القالب الرئيسي للتقارير)
        ├── partials/
        │   ├── header.blade.php              (رأس التقرير - شعار الشركة)
        │   └── footer.blade.php              (تذييل التقرير)
        ├── attendance/
        │   ├── monthly.blade.php
        │   ├── first-last.blade.php
        │   ├── time-records.blade.php
        │   └── date-range.blade.php
        ├── timesheet.blade.php
        ├── payroll.blade.php
        ├── loans.blade.php
        ├── leaves.blade.php
        ├── awards.blade.php
        ├── promotions.blade.php
        ├── resignations.blade.php
        ├── terminations.blade.php
        ├── transfers.blade.php
        ├── residence-renewals.blade.php
        ├── expiring-contracts.blade.php
        ├── expiring-documents.blade.php
        ├── employees-by-branch.blade.php
        ├── employees-by-country.blade.php
        └── end-of-service.blade.php
```

---

## المكون 1: DTOs (Data Transfer Objects)

### [NEW] `app/DTOs/Report/AttendanceReportFilterDTO.php`

DTO للتقارير المتعلقة بالحضور (سيُستخدم لـ 4 تقارير: الشهر، الأول والأخير، السجلات، النطاق الزمني)

-   `employee_id` - رقم الموظف (اختياري - all للجميع)
-   `month` - الشهر (YYYY-MM)
-   `start_date` / `end_date` - النطاق الزمني
-   `report_type` - نوع التقرير (monthly, first_last, time_records, date_range)

### [NEW] DTOs أخرى لكل نوع تقرير...

---

## المكون 2: Request Validations

### [NEW] `app/Http/Requests/Report/AttendanceReportRequest.php`

```php
public function rules(): array
{
    return [
        'report_type' => 'required|in:monthly,first_last,time_records,date_range',
        'employee_id' => 'nullable|integer|exists:ci_users,user_id',
        'month' => 'required_if:report_type,monthly|date_format:Y-m',
        'start_date' => 'required_if:report_type,date_range|date',
        'end_date' => 'required_if:report_type,date_range|date|after_or_equal:start_date'
    ];
}
```

---

## المكون 3: Repository Pattern

### [NEW] `app/Repository/Interface/ReportRepositoryInterface.php`

واجهة تحدد جميع العمليات المطلوبة لجلب بيانات التقارير.

### [NEW] `app/Repository/ReportRepository.php`

تنفيذ Repository مع Methods لكل تقرير:

**تقارير مكتملة (من modules موجودة):**

-   `getAttendanceMonthlyReport()` ✅
-   `getAttendanceFirstLastReport()` ✅
-   `getAttendanceTimeRecordsReport()` ✅
-   `getAttendanceDateRangeReport()` ✅
-   `getLoanReport()` ✅
-   `getLeaveReport()` ✅
-   `getResignationsReport()` ✅
-   `getTransfersReport()` ✅
-   `getEmployeesByBranchReport()` ✅
-   `getEmployeesByCountryReport()` ✅
-   `getTimesheetReport()` ✅ (من ci_timesheet)

**تقارير Placeholder (سيتم تنفيذها لاحقاً):**

-   `getPayrollReport()` ⚠️
-   `getAwardsReport()` ⚠️
-   `getPromotionsReport()` ⚠️
-   `getTerminationsReport()` ⚠️
-   `getResidenceRenewalReport()` ⚠️
-   `getExpiringContractsReport()` ⚠️
-   `getExpiringDocumentsReport()` ⚠️
-   `getEndOfServiceReport()` ⚠️
-   `getAccountStatementReport()` ⚠️

---

## المكون 4: Services

### [NEW] `app/Services/ReportService.php`

Service رئيسي يحتوي على:

-   منطق التحقق من الصلاحيات (Hierarchy Logic)
-   تنسيق البيانات
-   استدعاء Repository
-   استدعاء PdfGeneratorService

### [NEW] `app/Services/PdfGeneratorService.php`

Service متخصص لإنشاء PDF:

-   إعدادات TCPDF
-   Header موحد للشركة
-   Footer موحد
-   دعم RTL للعربية
-   دعم الخطوط العربية (DejaVu Sans)
-   تحويل Blade View إلى HTML ثم PDF

---

## المكون 5: Controller

### [NEW] `app/Http/Controllers/Api/ReportController.php`

```php
// Attendance Reports
GET  /api/reports/attendance/monthly          → attendanceMonthly()
GET  /api/reports/attendance/first-last       → attendanceFirstLast()
GET  /api/reports/attendance/time-records     → attendanceTimeRecords()
GET  /api/reports/attendance/date-range       → attendanceDateRange()

// Timesheet Report
GET  /api/reports/timesheet                   → timesheet()

// Payroll Report
GET  /api/reports/payroll                     → payroll()            ⚠️ Placeholder

// Loans Report
GET  /api/reports/loans                       → loans()

// Leave Report
GET  /api/reports/leaves                      → leaves()

// Awards Report
GET  /api/reports/awards                      → awards()             ⚠️ Placeholder

// Promotions Report
GET  /api/reports/promotions                  → promotions()         ⚠️ Placeholder

// Resignations Report
GET  /api/reports/resignations                → resignations()

// Terminations Report
GET  /api/reports/terminations                → terminations()       ⚠️ Placeholder

// Transfers Report
GET  /api/reports/transfers                   → transfers()

// Residence Renewal Report
GET  /api/reports/residence-renewals          → residenceRenewals()  ⚠️ Placeholder

// Expiring Contracts Report
GET  /api/reports/expiring-contracts          → expiringContracts()  ⚠️ Placeholder

// Expiring Documents Report
GET  /api/reports/expiring-documents          → expiringDocuments()  ⚠️ Placeholder

// Employees by Branch Report
GET  /api/reports/employees-by-branch         → employeesByBranch()

// Employees by Country Report
GET  /api/reports/employees-by-country        → employeesByCountry()

// End of Service Report
GET  /api/reports/end-of-service              → endOfService()       ⚠️ Placeholder
```

---

## المكون 6: Routes

### [MODIFY] `routes/api.php`

```php
// Reports Management - صلاحية موحدة: system_reports
Route::prefix('reports')->middleware('simple.permission:system_reports')->group(function () {
    // Attendance Reports
    Route::get('/attendance/monthly', [ReportController::class, 'attendanceMonthly']);
    Route::get('/attendance/first-last', [ReportController::class, 'attendanceFirstLast']);
    Route::get('/attendance/time-records', [ReportController::class, 'attendanceTimeRecords']);
    Route::get('/attendance/date-range', [ReportController::class, 'attendanceDateRange']);

    // Timesheet Report
    Route::get('/timesheet', [ReportController::class, 'timesheet']);

    // Financial Reports
    Route::get('/payroll', [ReportController::class, 'payroll']);
    Route::get('/loans', [ReportController::class, 'loans']);

    // HR Reports
    Route::get('/leaves', [ReportController::class, 'leaves']);
    Route::get('/awards', [ReportController::class, 'awards']);
    Route::get('/promotions', [ReportController::class, 'promotions']);
    Route::get('/resignations', [ReportController::class, 'resignations']);
    Route::get('/terminations', [ReportController::class, 'terminations']);
    Route::get('/transfers', [ReportController::class, 'transfers']);

    // Document Expiry Reports
    Route::get('/residence-renewals', [ReportController::class, 'residenceRenewals']);
    Route::get('/expiring-contracts', [ReportController::class, 'expiringContracts']);
    Route::get('/expiring-documents', [ReportController::class, 'expiringDocuments']);

    // Employee Reports
    Route::get('/employees-by-branch', [ReportController::class, 'employeesByBranch']);
    Route::get('/employees-by-country', [ReportController::class, 'employeesByCountry']);

    // End of Service
    Route::get('/end-of-service', [ReportController::class, 'endOfService']);
});
```

---

## ترتيب التنفيذ المقترح

### المرحلة 1: البنية الأساسية

1. إنشاء `PdfGeneratorService.php` (core service)
2. إنشاء `ReportRepositoryInterface.php` و `ReportRepository.php`
3. إنشاء `ReportService.php`
4. إنشاء `ReportController.php` (مع جميع الـ methods بما فيها Placeholders)
5. إنشاء Blade Views الأساسية (layout, header, footer)
6. تسجيل Routes

### المرحلة 2: تقارير الحضور (4 تقارير) ✅

-   DTOs + Requests للحضور
-   Repository methods للحضور
-   Blade Views للحضور

### المرحلة 3: التقارير من Modules موجودة ✅

-   السلف (AdvanceSalary)
-   الإجازات (Leave)
-   الاستقالات (Resignation)
-   التحويلات (Transfer)
-   الموظفين حسب الفرع/الدولة (Employee)

### المرحلة 4: التقارير Placeholder ⚠️

سيتم إنشاء endpoints فارغة ترجع رسالة:

```json
{
    "success": false,
    "message": "هذا التقرير قيد التطوير",
    "report_name": "..."
}
```

---

## ملاحظات تقنية

1. **TCPDF موجود** في `composer.json` (`tecnickcom/tcpdf: ^6.10`)
2. **mpdf موجود أيضاً** كبديل
3. **PDF فقط** - لا حاجة لـ CSV أو JSON
4. **Blade Views** لإنشاء قوالب التقارير (HTML → PDF)
5. **صلاحية موحدة**: `system_reports`
6. **Hierarchy Logic** سيُطبق على جميع التقارير
7. دعم **RTL** مطلوب للعربية

---

## الملفات الجديدة (ملخص)

| النوع       | العدد   | الملفات                               |
| ----------- | ------- | ------------------------------------- |
| DTOs        | ~15     | `app/DTOs/Report/*.php`               |
| Requests    | ~15     | `app/Http/Requests/Report/*.php`      |
| Repository  | 2       | Interface + Implementation            |
| Services    | 2       | ReportService + PdfGeneratorService   |
| Controller  | 1       | ReportController.php                  |
| Views       | ~20     | `resources/views/reports/*.blade.php` |
| **المجموع** | **~55** | ملف جديد                              |

---

## ✅ تم الاتفاق على:

1. ✅ الهيكلة المقترحة مناسبة
2. ✅ استخدام **Blade Views** لقوالب التقارير
3. ✅ صلاحية **موحدة**: `system_reports`
4. ✅ **PDF فقط** (بدون CSV)
5. ✅ لا تقارير إضافية
6. ✅ التقارير التي لم يُنشأ لها module ستكون **Placeholder**

---

## ⏳ الحالة: في انتظار الموافقة للبدء بالتنفيذ
