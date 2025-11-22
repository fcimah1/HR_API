# ملخص التحديثات - Leave Type Update Summary

## التاريخ: 2025-11-22

## نظرة عامة
تم تحديث نظام إدارة أنواع الإجازات لدعم جميع الحقول المطلوبة وفقاً للمتطلبات.

---

## 🔄 التغييرات الرئيسية

### 1. بنية قاعدة البيانات (ci_erp_constants)

#### field_one (Serialized Array)
**ملاحظة مهمة:** جميع القيم مخزنة كـ **strings** في المصفوفة المسلسلة.

```php
// مثال على البيانات المخزنة
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
        // ... باقي العناصر
    }
}
```

#### field_two
- القيمة: `0` أو `1` (integer)
- المعنى: يتطلب الموافقة

#### field_three
- القيمة: `0` أو `1` (integer)
- المعنى: إجازة مدفوعة

---

## 📁 الملفات المحدثة

### 1. UpdateLeaveTypeRequest.php
**المسار:** `app/Http/Requests/Leave/UpdateLeaveTypeRequest.php`

**التحديثات:**
- ✅ إضافة قواعد التحقق لجميع الحقول الجديدة
- ✅ إضافة رسائل الخطأ المخصصة باللغة العربية

**الحقول المضافة:**
```php
'is_paid_leave' => 'nullable|boolean',
'enable_leave_accrual' => 'nullable|boolean',
'is_carry' => 'nullable|boolean',
'carry_limit' => 'nullable|numeric|min:0',
'is_negative_quota' => 'nullable|boolean',
'negative_limit' => 'nullable|numeric|min:0',
'is_quota' => 'nullable|boolean',
'quota_assign' => 'nullable|array',
'quota_assign.*' => 'nullable|numeric|min:0',
```

---

### 2. CreateLeaveTypeRequest.php
**المسار:** `app/Http/Requests/Leave/CreateLeaveTypeRequest.php`

**التحديثات:**
- ✅ نفس قواعد التحقق كـ UpdateLeaveTypeRequest
- ✅ رسائل خطأ مخصصة

---

### 3. UpdateLeaveTypeDTO.php
**المسار:** `app/DTOs/Leave/UpdateLeaveTypeDTO.php`

**التحديثات:**
- ✅ إضافة جميع الخصائص الجديدة
- ✅ تحديث `fromRequest()` لقراءة جميع الحقول
- ✅ **تحديث `toArray()` لتحويل جميع القيم إلى strings**

**الكود الرئيسي:**
```php
public function toArray(): array
{
    // تحويل quota_assign إلى strings
    $quotaAssignStrings = [];
    foreach ($this->quotaAssign as $key => $value) {
        $quotaAssignStrings[$key] = (string) $value;
    }

    $options = [
        'enable_leave_accrual' => (string) ($this->enableLeaveAccrual ? 1 : 0),
        'is_carry' => (string) ($this->isCarry ? 1 : 0),
        'carry_limit' => (string) $this->carryLimit,
        'is_negative_quota' => (string) ($this->isNegativeQuota ? 1 : 0),
        'negative_limit' => (string) $this->negativeLimit,
        'is_quota' => (string) ($this->isQuota ? 1 : 0),
        'quota_assign' => $quotaAssignStrings,
    ];

    return [
        'category_name' => $this->name,
        'field_one' => serialize($options),
        'field_two' => $this->requiresApproval ? 1 : 0,
        'field_three' => $this->isPaidLeave ? 1 : 0,
        'updated_at' => now()->format('Y-m-d H:i:s'),
    ];
}
```

---

### 4. CreateLeaveTypeDTO.php
**المسار:** `app/DTOs/Leave/CreateLeaveTypeDTO.php`

**التحديثات:**
- ✅ نفس التحديثات كـ UpdateLeaveTypeDTO
- ✅ تحويل جميع القيم إلى strings قبل التسلسل

---

### 5. LeaveController.php
**المسار:** `app/Http/Controllers/Api/LeaveController.php`

**التحديثات:**
- ✅ تحديث توثيق Swagger لـ `updateLeaveType()`
- ✅ إضافة وصف لجميع الحقول الجديدة

---

## 🆕 الحقول الجديدة

| الحقل | النوع | الموقع | الوصف |
|------|------|--------|-------|
| `is_paid_leave` | boolean | field_three | إجازة مدفوعة |
| `enable_leave_accrual` | boolean | field_one | تمكين استحقاق الإجازة |
| `is_carry` | boolean | field_one | الترحيل |
| `carry_limit` | number | field_one | الحد المتاح للترحيل |
| `is_negative_quota` | boolean | field_one | رصيد الإدارة |
| `negative_limit` | number | field_one | رصيد الحادثة المستحق |
| `is_quota` | boolean | field_one | تخصيص النسبة السنوية |
| `quota_assign` | array[50] | field_one | تخصيص الساعات للسنوات |

---

## 📝 مثال على الاستخدام

### Request Body
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
        "2": 150,
        "3": 0,
        "4": 0
        // ... باقي العناصر حتى 49
    }
}
```

### Response
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
    },
    "created by": "اسم المستخدم"
}
```

### البيانات المخزنة في field_one
```php
a:7:{
    s:20:"enable_leave_accrual";s:1:"1";
    s:8:"is_carry";s:1:"0";
    s:11:"carry_limit";s:1:"0";
    s:17:"is_negative_quota";s:1:"0";
    s:14:"negative_limit";s:1:"0";
    s:8:"is_quota";s:1:"1";
    s:12:"quota_assign";a:50:{
        i:0;s:2:"50";
        i:1;s:3:"100";
        i:2;s:3:"150";
        i:3;s:1:"0";
        i:4;s:1:"0";
        // ...
    }
}
```

---

## ✅ النقاط المهمة

### 1. تحويل القيم إلى Strings
**لماذا؟** لأن PHP serialize يخزن القيم كـ strings في المصفوفة المسلسلة.

```php
// ❌ خطأ - سيخزن كـ integers
'is_carry' => 0

// ✅ صحيح - سيخزن كـ string
'is_carry' => (string) 0  // النتيجة: "0"
```

### 2. quota_assign Array
- يجب أن يحتوي على 50 عنصر (من 0 إلى 49)
- كل عنصر يمثل عدد الساعات لسنة معينة
- العنصر 49 يمثل السنة 50 فأكثر
- جميع القيم يجب أن تكون strings

### 3. القيم الافتراضية
```php
'requires_approval' => true (افتراضي)
'is_paid_leave' => false (افتراضي)
'enable_leave_accrual' => false (افتراضي)
'is_carry' => false (افتراضي)
'carry_limit' => 0 (افتراضي)
'is_negative_quota' => false (افتراضي)
'negative_limit' => 0 (افتراضي)
'is_quota' => true (افتراضي)
'quota_assign' => [] (افتراضي)
```

---

## 🧪 الاختبار

### 1. اختبار التحديث البسيط
```bash
curl -X PUT "http://localhost/api/leaves/types/1" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"leave_type_name": "إجازة محدثة"}'
```

### 2. اختبار التحديث الكامل
```bash
curl -X PUT "http://localhost/api/leaves/types/1" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
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
}'
```

---

## 📚 الملفات الإضافية

### 1. LEAVE_TYPE_UPDATE_API.md
- توثيق كامل للـ API
- أمثلة على الاستخدام
- شرح البنية

### 2. LEAVE_TYPE_FRONTEND_EXAMPLES.js
- أمثلة JavaScript/Frontend
- دوال مساعدة
- أمثلة Vue.js و React

---

## 🔍 التحقق من البيانات

للتحقق من أن البيانات مخزنة بشكل صحيح:

```sql
-- عرض البيانات المسلسلة
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

---

## 🎯 الخلاصة

✅ تم تحديث جميع الملفات المطلوبة
✅ تم ضمان توافق البيانات مع البنية المخزنة
✅ تم إضافة التحقق من الصحة لجميع الحقول
✅ تم توثيق API بشكل كامل
✅ تم إنشاء أمثلة للاستخدام

---

## 📞 الدعم

إذا واجهت أي مشاكل:
1. تحقق من صلاحيات المستخدم (leave_type3)
2. تحقق من صحة البيانات المرسلة
3. راجع logs في `storage/logs/laravel.log`
4. تحقق من البيانات المخزنة في قاعدة البيانات

---

**تم بنجاح! ✨**
