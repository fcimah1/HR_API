# تحديث نوع الإجازة - Leave Type Update API

## نظرة عامة

تم تحديث API لتحديث نوع الإجازة لدعم جميع الحقول المطلوبة وفقاً للصورة المرفقة.

## بنية جدول `ci_erp_constants`

| العمود | الوصف | القيمة |
|--------|-------|--------|
| `constants_id` | معرف النوع | Auto increment |
| `company_id` | معرف الشركة | Integer |
| `type` | نوع الثابت | 'leave_type' |
| `category_name` | اسم نوع الإجازة | String |
| `field_one` | خيارات إضافية (serialized) | Serialized Array |
| `field_two` | يتطلب الموافقة | 0 أو 1 |
| `field_three` | إجازة مدفوعة | 0 أو 1 |
| `created_at` | تاريخ الإنشاء | DateTime |

## توزيع الحقول

### field_one (Serialized Array)
يحتوي على الحقول التالية:
```php
[
    'enable_leave_accrual' => 0 أو 1,  // تمكين استحقاق الإجازة
    'is_carry' => 0 أو 1,               // الترحيل
    'carry_limit' => رقم,               // الحد المتاح للترحيل
    'is_negative_quota' => 0 أو 1,     // رصيد الإدارة
    'negative_limit' => رقم,            // رصيد الحادثة المستحق
    'is_quota' => 0 أو 1,              // تخصيص النسبة السنوية
    'quota_assign' => [                // مصفوفة من 50 عنصر (0-49)
        0 => رقم,  // السنة الأولى
        1 => رقم,  // السنة الثانية
        ...
        49 => رقم  // السنة الخمسون أو أكثر
    ]
]
```

### field_two
- `1` = يتطلب الموافقة
- `0` = لا يتطلب الموافقة

### field_three
- `1` = إجازة مدفوعة
- `0` = إجازة غير مدفوعة

## API Endpoint

### تحديث نوع الإجازة
**PUT** `/api/leaves/types/{id}`

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Request Body
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
        "0": 24,
        "1": 24,
        "2": 24,
        "3": 24,
        "4": 24,
        "5": 30,
        "6": 30,
        "7": 30,
        "8": 30,
        "9": 30,
        "10": 36,
        ...
        "49": 45
    }
}
```

#### Response (Success)
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

#### Response (Error)
```json
{
    "success": false,
    "message": "فشل في تحديث نوع الإجازة",
    "error": "نوع الإجازة غير موجود",
    "created by": "اسم المستخدم"
}
```

## الحقول المطلوبة

| الحقل | النوع | مطلوب | الوصف |
|------|------|-------|-------|
| `leave_type_name` | string | نعم | اسم نوع الإجازة |
| `requires_approval` | boolean | لا | يتطلب الموافقة (افتراضي: true) |
| `is_paid_leave` | boolean | لا | إجازة مدفوعة (افتراضي: false) |
| `enable_leave_accrual` | boolean | لا | تمكين استحقاق الإجازة (افتراضي: false) |
| `is_carry` | boolean | لا | الترحيل (افتراضي: false) |
| `carry_limit` | number | لا | الحد المتاح للترحيل (افتراضي: 0) |
| `is_negative_quota` | boolean | لا | رصيد الإدارة (افتراضي: false) |
| `negative_limit` | number | لا | رصيد الحادثة المستحق (افتراضي: 0) |
| `is_quota` | boolean | لا | تخصيص النسبة السنوية (افتراضي: true) |
| `quota_assign` | array | لا | تخصيص النسبة السنوية (50 عنصر) |

## قواعد التحقق

- `leave_type_name`: يجب أن يكون نص، بحد أقصى 255 حرف، فريد في الجدول
- `requires_approval`: يجب أن يكون قيمة منطقية (true/false أو 1/0)
- `is_paid_leave`: يجب أن يكون قيمة منطقية
- `enable_leave_accrual`: يجب أن يكون قيمة منطقية
- `is_carry`: يجب أن يكون قيمة منطقية
- `carry_limit`: يجب أن يكون رقم >= 0
- `is_negative_quota`: يجب أن يكون قيمة منطقية
- `negative_limit`: يجب أن يكون رقم >= 0
- `is_quota`: يجب أن يكون قيمة منطقية
- `quota_assign`: يجب أن يكون مصفوفة، كل عنصر يجب أن يكون رقم >= 0

## الصلاحيات المطلوبة

- يجب أن يكون المستخدم مسجل دخول
- يجب أن يكون لديه صلاحية `leave_type3`

## أمثلة الاستخدام

### مثال 1: تحديث اسم نوع الإجازة فقط
```json
{
    "leave_type_name": "إجازة تعليمية محدثة"
}
```

### مثال 2: تحديث مع تفعيل الترحيل
```json
{
    "leave_type_name": "إجازة سنوية",
    "requires_approval": true,
    "is_paid_leave": true,
    "is_carry": true,
    "carry_limit": 10
}
```

### مثال 3: تحديث مع تخصيص النسبة السنوية
```json
{
    "leave_type_name": "إجازة سنوية",
    "is_quota": true,
    "quota_assign": {
        "0": 21,
        "1": 21,
        "2": 21,
        "3": 21,
        "4": 21,
        "5": 26,
        "6": 26,
        "7": 26,
        "8": 26,
        "9": 26,
        "10": 30
    }
}
```

## ملاحظات مهمة

1. **quota_assign**: يمكن أن تحتوي على 50 عنصر (من 0 إلى 49)، حيث:
   - العناصر من 0 إلى 48 تمثل السنوات من 1 إلى 49
   - العنصر 49 يمثل السنة 50 فأكثر

2. **field_one**: يتم تخزين جميع الخيارات الإضافية في هذا الحقل كـ serialized array

3. **field_two**: يخزن قيمة `requires_approval` (1 أو 0)

4. **field_three**: يخزن قيمة `is_paid_leave` (1 أو 0)

## الملفات المحدثة

1. `app/Http/Requests/Leave/UpdateLeaveTypeRequest.php` - قواعد التحقق
2. `app/Http/Requests/Leave/CreateLeaveTypeRequest.php` - قواعد التحقق للإنشاء
3. `app/DTOs/Leave/UpdateLeaveTypeDTO.php` - DTO للتحديث
4. `app/DTOs/Leave/CreateLeaveTypeDTO.php` - DTO للإنشاء
5. `app/Http/Controllers/Api/LeaveController.php` - توثيق Swagger

## اختبار API

يمكنك اختبار API باستخدام:
- Postman
- Swagger UI (متاح في `/api/documentation`)
- cURL

### مثال cURL
```bash
curl -X PUT "http://your-domain/api/leaves/types/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
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
        "0": 24,
        "1": 24,
        "2": 24
    }
}'
```
