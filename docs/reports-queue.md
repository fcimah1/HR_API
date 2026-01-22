# نظام تقارير الخلفية (Queue-based Reports System)

يوثق هذا الملف نظام توليد التقارير غير المتزامن (Asynchronous Reporting) الذي تم تنفيذه لتحسين الأداء وتجربة المستخدم.

---

## 🏗️ نظرة عامة (Architecture)

يعتمد النظام على Laravel Jobs & Queue لفصل عملية توليد التقارير الثقيلة (PDF Generation) عن دورة حياة الطلب (Request Lifecycle).

### المكونات الرئيسية:

1. **Database**: جدول `ci_erp_generated_reports` لتخزين حالة التقارير.
2. **Model**: `GeneratedReport` لإدارة الحالة والملفات.
3. **Controller**: `AsyncReportController` لاستقبال الطلبات وعرض النتائج.
4. **Job**: `GenerateReportJob` للمعالجة في الخلفية.
5. **Services**:
    - `ReportService`: لجلب البيانات (Integration Point).
    - `ReportExportService`: لتوليد PDF وحفظه.
    - `PdfGeneratorService`: المُحرك الأساسي لـ TCPDF.

---

## 📡 API Endpoints

جميع المسارات محمية بـ `auth:api` وتبدأ بـ `/api/reports`.

### 1. طلب توليد تقرير (Background Request)

```http
POST /generate-async/{type}
```

**المعلمات (Path):**

- `type`: نوع التقرير (مثل: `attendance_monthly`, `timesheet`, وغيرها).

**جسم الطلب (Body) - JSON:**
يعتمد على فلاتر التقرير، مثال لتقرير الحضور:

```json
{
    "month": "2026-01",
    "employee_id": 123,
    "branch_id": 5
}
```

**الرد الناجح:**

```json
{
    "message": "تم إضافة التقرير للمعالجة",
    "report_id": 55,
    "status": "pending",
    "estimated_time": "1-5 دقائق"
}
```

---

### 2. عرض التقارير المولدة

```http
GET /generated
```

يعرض قائمة بتقارير المستخدم الحالي (pagination).

---

### 3. تحميل ملف التقرير

```http
GET /generated/{id}/download
```

يحمل ملف PDF إذا كانت حالته `completed`.

---

### 4. حذف تقرير

```http
DELETE /generated/{id}
```

يحذف السجل وملف الـ PDF من السيرفر.

---

## ⚙️ إعدادات السيرفر (Server Config)

لضمان عمل النظام، يجب تشغيل **Queue Worker** و **Scheduler**.

### 1. Queue Worker (المُعالج)

يجب تشغيله باستمرار لمعالجة الطلبات.

**الخيار الأفضل (Supervisor):**

```ini
[program:hr-reports-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --queue=reports,default --tries=3 --timeout=300
autostart=true
autorestart=true
user=www-data
numprocs=2
```

**الخيار البديل (Cron - الموجود حالياً):**

```bash
* * * * * php /path/to/project/artisan queue:work --stop-when-empty --tries=3
```

### 2. Scheduler (الصيانة)

تمت إضافة أمر لتنظيف التقارير القديمة تلقائياً.
يجب التأكد من وجود Cron Entry الأساسي لـ Laravel:

```bash
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

**مهام الصيانة المجدولة:**

- `reports:cleanup --days=7` (يومياً الساعة 01:00 صباحاً): يحذف التقارير والملفات الأقدم من 7 أيام.

---

## 🛠️ دليل التطوير والتكامل (Developer Guide)

### إضافة تقرير جديد للنظام

لدمج تقرير موجود (مثلاً `attendance_monthly`) مع نظام الـ Queue:

#### الخطوة 1: تحديث `ReportExportService`

أضف منطق حفظ الملف بدلاً من تحميله. استخدم دالة `saveToFile()` الجديدة في `PdfGeneratorService`.

```php
// في ReportExportService.php
public function generateAndSavePdf(...) {
    // ... منطق التوليد ...
    $this->pdfGenerator->saveToFile($savePath);
    return true;
}
```

#### الخطوة 2: تحديث `GenerateReportJob`

قم بربط نوع التقرير بجلب البيانات في دالة `fetchReportData`:

```php
// في GenerateReportJob.php
private function fetchReportData(...) {
    switch ($this->reportType) {
        case 'mypdfreport':
             // استدعاء Repository لجلب البيانات
             return $repo->getMyReportData($this->filters);
    }
}
```

---

## 🔔 التنبيهات (Notifications)

يقوم النظام بإرسال **Push Notifications** للمستخدم تلقائياً عند انتهاء المعالجة باستخدام FCM.

- **نجاح المعالجة**: رسالة "التقرير جاهز" عند اكتمال إنشاء الملف.
- **فشل المعالجة**: رسالة "فشل التقرير" عند حدوث خطأ.

---

## 📂 التخزين (File Storage)

يتم تخزين ملفات التقارير في المسار العام لسهولة الوصول (بشكل آمن):
`storage/app/public/reports/Y/m/`

ملاحظة: تأكد من عمل `storage:link` إذا كنت تريد الوصول المباشر عبر Web Server، لكن الـ API يقوم بتحميل الملف عبر PHP (`downloadGenerated`) للتحقق من الصلاحيات.

---

## 📊 حالات التقرير (Statuses)

| الحالة       | الوصف                                                      |
| ------------ | ---------------------------------------------------------- |
| `pending`    | في الانتظار (لم يبدأ الـ Worker المعالجة بعد)              |
| `processing` | جاري العمل حالياً                                          |
| `completed`  | انتهى بنجاح، تم التخزين في `public/reports` وإرسال الإشعار |
| `failed`     | فشل التوليد (راجع `error_message` لمعرفة السبب)            |

---

_تم التحديث: 2026-01-19 (بما يشمل التنبيهات)_
