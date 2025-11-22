# تحديث نظام أنواع الإجازات - Leave Type System Update

## 📋 نظرة عامة

تم تحديث نظام إدارة أنواع الإجازات بنجاح لدعم جميع الحقول المطلوبة وفقاً للمتطلبات.

---

## ✅ ما تم إنجازه

### 1. تحديث الملفات الأساسية

| الملف | الحالة | الوصف |
|------|--------|-------|
| `UpdateLeaveTypeRequest.php` | ✅ محدث | قواعد التحقق + رسائل الخطأ |
| `CreateLeaveTypeRequest.php` | ✅ محدث | قواعد التحقق + رسائل الخطأ |
| `UpdateLeaveTypeDTO.php` | ✅ محدث | معالجة البيانات + تحويل إلى strings |
| `CreateLeaveTypeDTO.php` | ✅ محدث | معالجة البيانات + تحويل إلى strings |
| `LeaveController.php` | ✅ محدث | توثيق Swagger API |

### 2. الحقول الجديدة المدعومة

- ✅ `is_paid_leave` - إجازة مدفوعة
- ✅ `enable_leave_accrual` - تمكين استحقاق الإجازة
- ✅ `is_carry` - الترحيل
- ✅ `carry_limit` - الحد المتاح للترحيل
- ✅ `is_negative_quota` - رصيد الإدارة
- ✅ `negative_limit` - رصيد الحادثة المستحق
- ✅ `is_quota` - تخصيص النسبة السنوية
- ✅ `quota_assign` - مصفوفة 50 عنصر للسنوات

### 3. ملفات التوثيق

| الملف | الوصف |
|------|-------|
| `LEAVE_TYPE_UPDATE_API.md` | توثيق كامل للـ API |
| `LEAVE_TYPE_FRONTEND_EXAMPLES.js` | أمثلة Frontend |
| `LEAVE_TYPE_UPDATES_SUMMARY.md` | ملخص التحديثات |
| `LEAVE_TYPE_SOFT_DELETE.md` | توثيق Soft Delete |
| `test_leave_type_serialization.php` | ملف اختبار |

---

## 🎯 النقطة الأهم: تحويل القيم إلى Strings

### المشكلة
البيانات في `field_one` مخزنة كـ **serialized array** حيث جميع القيم هي **strings**.

### الحل
تم تحديث `toArray()` في كل من `UpdateLeaveTypeDTO` و `CreateLeaveTypeDTO` لتحويل جميع القيم إلى strings:

```php
// ❌ الطريقة القديمة (خطأ)
$options = [
    'is_carry' => 0,  // سيخزن كـ integer
    'quota_assign' => [0 => 50]  // سيخزن كـ integer
];

// ✅ الطريقة الجديدة (صحيح)
$options = [
    'is_carry' => (string) 0,  // سيخزن كـ "0"
    'quota_assign' => [0 => "50"]  // سيخزن كـ "50"
];
```

---

## 📊 بنية البيانات

### field_one (Serialized)
```php
a:7:{
    s:20:"enable_leave_accrual";s:1:"0";
    s:8:"is_carry";s:1:"0";
    s:11:"carry_limit";s:1:"0";
    s:17:"is_negative_quota";s:1:"0";
    s:14:"negative_limit";s:1:"0";
    s:8:"is_quota";s:1:"1";
    s:12:"quota_assign";a:50:{
        i:0;s:2:"50";
        i:1;s:3:"100";
        i:2;s:3:"150";
        // ...
    }
}
```

### field_two
- `1` = يتطلب الموافقة
- `0` = لا يتطلب الموافقة

### field_three (الحالة النشطة + إجازة مدفوعة)
- `1` = نشط وإجازة مدفوعة
- `0` = غير نشط أو إجازة غير مدفوعة

**ملاحظة:** يتم استخدام `field_three` أيضاً للتحقق من الحالة النشطة (Soft Delete).

---

## 🚀 كيفية الاستخدام

### 1. تحديث نوع الإجازة

**Endpoint:** `PUT /api/leaves/types/{id}`

**Request:**
```json
{
    "leave_type_name": "إجازة دراسية",
    "requires_approval": true,
    "is_paid_leave": false,
    "enable_leave_accrual": true,
    "is_carry": false,
    "carry_limit": 0,
    "is_negative_quota": false,
    "negative_limit": 0,
    "is_quota": true,
    "quota_assign": {
        "0": 50,
        "1": 100,
        "2": 150
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "تم تحديث نوع الإجازة بنجاح",
    "data": {
        "leave_type_id": 1,
        "leave_type_name": "إجازة دراسية",
        "leave_type_short_name": "إجازة دراسية",
        "leave_days": 1,
        "leave_type_status": true,
        "company_id": 24
    }
}
```

### 2. إنشاء نوع إجازة جديد

**Endpoint:** `POST /api/leaves/types`

نفس البنية كـ Update.

### 3. إلغاء تفعيل نوع الإجازة (Soft Delete)

**Endpoint:** `DELETE /api/leaves/types/{id}`

**ملاحظة مهمة:** لا يتم حذف النوع فعلياً، بل يتم إلغاء تفعيله بتعيين `field_three = 0`.

**Response:**
```json
{
    "success": true,
    "message": "تم إلغاء تفعيل نوع الإجازة بنجاح"
}
```

**الفوائد:**
- ✅ الحفاظ على البيانات التاريخية
- ✅ يمكن إعادة التفعيل لاحقاً
- ✅ الطلبات القديمة لا تتأثر

**لمزيد من التفاصيل:** راجع `LEAVE_TYPE_SOFT_DELETE.md`

---

## 🧪 الاختبار

### اختبار يدوي
```bash
# تشغيل ملف الاختبار
php test_leave_type_serialization.php
```

### اختبار API
```bash
curl -X PUT "http://localhost/api/leaves/types/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "leave_type_name": "إجازة محدثة",
    "requires_approval": true,
    "is_paid_leave": false,
    "enable_leave_accrual": true,
    "quota_assign": {
        "0": 50,
        "1": 100,
        "2": 150
    }
}'
```

---

## 📝 ملاحظات مهمة

### 1. quota_assign
- يجب أن يحتوي على **50 عنصر** (من 0 إلى 49)
- العنصر 0 = السنة الأولى
- العنصر 49 = السنة 50 فأكثر
- جميع القيم يجب أن تكون أرقام (سيتم تحويلها إلى strings تلقائياً)

### 2. القيم الافتراضية
إذا لم يتم إرسال حقل معين، سيتم استخدام القيمة الافتراضية:
- `requires_approval`: `true`
- `is_paid_leave`: `false`
- `enable_leave_accrual`: `false`
- `is_carry`: `false`
- `carry_limit`: `0`
- `is_negative_quota`: `false`
- `negative_limit`: `0`
- `is_quota`: `true`
- `quota_assign`: `[]`

### 3. الصلاحيات
- **الإنشاء:** `leave_type2`
- **التحديث:** `leave_type3`
- **الحذف:** `leave_type4`
- **العرض:** `leave_type1`

---

## 🔍 التحقق من البيانات

### في قاعدة البيانات
```sql
SELECT 
    constants_id,
    category_name,
    field_one,
    field_two,
    field_three
FROM ci_erp_constants
WHERE type = 'leave_type'
AND constants_id = 1;
```

### فك تشفير field_one
```php
$data = unserialize($field_one);
print_r($data);
```

---

## 📚 الموارد الإضافية

### التوثيق
- `LEAVE_TYPE_UPDATE_API.md` - توثيق شامل للـ API
- `LEAVE_TYPE_UPDATES_SUMMARY.md` - ملخص التحديثات

### أمثلة الكود
- `LEAVE_TYPE_FRONTEND_EXAMPLES.js` - أمثلة JavaScript/Vue/React

### الاختبار
- `test_leave_type_serialization.php` - اختبار التسلسل

---

## 🐛 استكشاف الأخطاء

### خطأ: "اسم نوع الإجازة موجود بالفعل"
- تحقق من أن الاسم فريد في الجدول
- أو قم بتغيير الاسم

### خطأ: "غير مصرح لك بتعديل أنواع الإجازات"
- تحقق من صلاحيات المستخدم
- يجب أن يكون لديه صلاحية `leave_type3`

### خطأ: "فشل في تحديث نوع الإجازة"
- راجع logs في `storage/logs/laravel.log`
- تحقق من صحة البيانات المرسلة

---

## ✨ الخلاصة

تم تحديث النظام بنجاح لدعم جميع الحقول المطلوبة مع ضمان:
- ✅ توافق البيانات مع البنية المخزنة
- ✅ تحويل جميع القيم إلى strings
- ✅ التحقق من صحة البيانات
- ✅ توثيق شامل
- ✅ أمثلة للاستخدام

**جاهز للاستخدام! 🎉**

---

## 📞 الدعم

إذا واجهت أي مشاكل أو كان لديك أسئلة:
1. راجع ملفات التوثيق
2. تحقق من logs
3. استخدم ملف الاختبار للتحقق من البنية

---

**آخر تحديث:** 2025-11-22
