# Employee Management API Documentation

## نظرة عامة

هذا التوثيق يغطي جميع endpoints الخاصة بإدارة الموظفين في نظام إدارة الموارد البشرية.

## المصادقة

جميع endpoints تتطلب مصادقة باستخدام Laravel Passport OAuth2 tokens.

```
Authorization: Bearer {access_token}
```

## الأذونات

- **Company Admin**: وصول كامل لجميع العمليات
- **HR Staff**: وصول محدود حسب الصلاحيات المحددة
- **Regular Employee**: وصول للبيانات الشخصية فقط

## Base URL

```
https://api.example.com/api
```

## Endpoints

### 1. قائمة الموظفين

```http
GET /employees
```

**الوصف**: استرجاع قائمة بجميع الموظفين في الشركة

**المعاملات**:
- `page` (اختياري): رقم الصفحة للتصفح
- `per_page` (اختياري): عدد العناصر في الصفحة (افتراضي: 15)
- `department_id` (اختياري): تصفية حسب القسم
- `designation_id` (اختياري): تصفية حسب المنصب
- `is_active` (اختياري): تصفية حسب الحالة (1 للنشط، 0 لغير النشط)

**مثال على الطلب**:
```http
GET /employees?page=1&per_page=20&department_id=5&is_active=1
```

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم استرجاع قائمة الموظفين بنجاح",
    "data": [
        {
            "user_id": 123,
            "first_name": "أحمد",
            "last_name": "محمد",
            "email": "ahmed.mohamed@company.com",
            "user_type": "staff",
            "is_active": 1,
            "company_id": 1,
            "created_at": "2024-01-15T10:30:00Z",
            "updated_at": "2024-01-15T10:30:00Z",
            "details": {
                "department": {
                    "department_id": 5,
                    "department_name": "قسم تقنية المعلومات"
                },
                "designation": {
                    "designation_id": 10,
                    "designation_name": "مطور برمجيات"
                },
                "hire_date": "2024-01-15",
                "basic_salary": 5000.00
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 150,
        "last_page": 8
    }
}
```

### 2. إنشاء موظف جديد

```http
POST /employees
```

**الوصف**: إضافة موظف جديد إلى النظام

**البيانات المطلوبة**:
```json
{
    "first_name": "string (مطلوب)",
    "last_name": "string (مطلوب)",
    "email": "string (مطلوب، فريد)",
    "password": "string (مطلوب، 8 أحرف على الأقل)",
    "user_type": "staff|company (مطلوب)",
    "is_active": "boolean (اختياري، افتراضي: true)",
    "department_id": "integer (مطلوب)",
    "designation_id": "integer (مطلوب)",
    "hire_date": "date (اختياري، افتراضي: اليوم)",
    "basic_salary": "decimal (اختياري)",
    "birth_date": "date (اختياري)",
    "gender": "male|female (اختياري)",
    "phone": "string (اختياري)",
    "address": "string (اختياري)"
}
```

**مثال على الطلب**:
```json
{
    "first_name": "سارة",
    "last_name": "أحمد",
    "email": "sara.ahmed@company.com",
    "password": "SecurePass123",
    "user_type": "staff",
    "department_id": 3,
    "designation_id": 7,
    "hire_date": "2024-02-01",
    "basic_salary": 4500.00,
    "birth_date": "1990-05-15",
    "gender": "female",
    "phone": "+966501234567"
}
```

**الاستجابة الناجحة** (201):
```json
{
    "success": true,
    "message": "تم إنشاء الموظف بنجاح",
    "data": {
        "user_id": 124,
        "first_name": "سارة",
        "last_name": "أحمد",
        "email": "sara.ahmed@company.com",
        "user_type": "staff",
        "is_active": 1,
        "company_id": 1,
        "created_at": "2024-02-01T09:00:00Z"
    }
}
```

### 3. عرض تفاصيل موظف

```http
GET /employees/{id}
```

**الوصف**: استرجاع تفاصيل موظف محدد

**المعاملات**:
- `id`: معرف الموظف (مطلوب)

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم استرجاع بيانات الموظف بنجاح",
    "data": {
        "user_id": 123,
        "first_name": "أحمد",
        "last_name": "محمد",
        "email": "ahmed.mohamed@company.com",
        "user_type": "staff",
        "is_active": 1,
        "company_id": 1,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z",
        "details": {
            "department": {
                "department_id": 5,
                "department_name": "قسم تقنية المعلومات"
            },
            "designation": {
                "designation_id": 10,
                "designation_name": "مطور برمجيات",
                "hierarchy_level": 3
            },
            "hire_date": "2024-01-15",
            "basic_salary": 5000.00,
            "birth_date": "1985-03-20",
            "gender": "male",
            "phone": "+966501234567",
            "address": "الرياض، المملكة العربية السعودية"
        }
    }
}
```

### 4. تحديث بيانات موظف

```http
PUT /employees/{id}
```

**الوصف**: تحديث بيانات موظف موجود

**البيانات القابلة للتحديث**:
```json
{
    "first_name": "string (اختياري)",
    "last_name": "string (اختياري)",
    "email": "string (اختياري، فريد)",
    "is_active": "boolean (اختياري)",
    "department_id": "integer (اختياري)",
    "designation_id": "integer (اختياري)",
    "basic_salary": "decimal (اختياري)",
    "birth_date": "date (اختياري)",
    "gender": "male|female (اختياري)",
    "phone": "string (اختياري)",
    "address": "string (اختياري)"
}
```

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم تحديث بيانات الموظف بنجاح",
    "data": {
        "user_id": 123,
        "first_name": "أحمد المحدث",
        "last_name": "محمد",
        "email": "ahmed.mohamed@company.com",
        "updated_at": "2024-02-01T14:30:00Z"
    }
}
```

### 5. حذف موظف

```http
DELETE /employees/{id}
```

**الوصف**: حذف موظف من النظام (حذف ناعم)

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم حذف الموظف بنجاح"
}
```

### 6. البحث في الموظفين

```http
GET /employees/search
```

**الوصف**: البحث في قاعدة بيانات الموظفين

**المعاملات**:
- `q`: نص البحث (مطلوب)
- `limit`: عدد النتائج المطلوبة (اختياري، افتراضي: 10)

**مثال على الطلب**:
```http
GET /employees/search?q=أحمد&limit=5
```

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم العثور على 3 نتائج",
    "data": [
        {
            "user_id": 123,
            "first_name": "أحمد",
            "last_name": "محمد",
            "email": "ahmed.mohamed@company.com",
            "department_name": "قسم تقنية المعلومات",
            "designation_name": "مطور برمجيات"
        }
    ]
}
```

### 7. إحصائيات الموظفين

```http
GET /employees/statistics
```

**الوصف**: الحصول على إحصائيات شاملة عن الموظفين

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم استرجاع الإحصائيات بنجاح",
    "data": {
        "total_employees": 150,
        "active_employees": 145,
        "inactive_employees": 5,
        "departments_count": 8,
        "designations_count": 25,
        "average_salary": 4750.50,
        "total_salary_cost": 688822.50,
        "employees_by_department": [
            {
                "department_name": "قسم تقنية المعلومات",
                "count": 35
            }
        ],
        "employees_by_designation": [
            {
                "designation_name": "مطور برمجيات",
                "count": 15
            }
        ],
        "employees_by_hierarchy": [
            {
                "hierarchy_level": 1,
                "level_name": "إدارة عليا",
                "count": 5
            }
        ],
        "by_gender": [
            {
                "gender": "male",
                "count": 90
            },
            {
                "gender": "female",
                "count": 60
            }
        ],
        "by_age_group": [
            {
                "age_group": "25-35",
                "count": 65
            }
        ],
        "recent_hires": [
            {
                "user_id": 124,
                "name": "سارة أحمد",
                "hire_date": "2024-02-01"
            }
        ],
        "salary_statistics": {
            "average": 4750.50,
            "minimum": 3000.00,
            "maximum": 12000.00,
            "total": 688822.50
        }
    }
}
```

### 8. الموظفين للمناوبة

```http
GET /employees/for-duty-employee
```

**الوصف**: الحصول على قائمة الموظفين المتاحين للمناوبة

**المعاملات**:
- `duty_employee_id`: معرف موظف المناوبة (مطلوب)

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم استرجاع قائمة الموظفين للمناوبة بنجاح",
    "data": [
        {
            "user_id": 123,
            "name": "أحمد محمد",
            "department": "قسم تقنية المعلومات",
            "designation": "مطور برمجيات"
        }
    ]
}
```

### 9. الموظفين للإشعارات

```http
GET /employees/for-notify
```

**الوصف**: الحصول على قائمة الموظفين لإرسال الإشعارات

**المعاملات**:
- `notification_type`: نوع الإشعار (مطلوب)

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم استرجاع قائمة الموظفين للإشعارات بنجاح",
    "data": [
        {
            "user_id": 123,
            "name": "أحمد محمد",
            "email": "ahmed.mohamed@company.com",
            "department": "قسم تقنية المعلومات"
        }
    ]
}
```

### 10. المرؤوسين

```http
GET /employees/subordinates
```

**الوصف**: الحصول على قائمة المرؤوسين لمدير معين

**المعاملات**:
- `manager_id`: معرف المدير (مطلوب)

**الاستجابة الناجحة** (200):
```json
{
    "success": true,
    "message": "تم استرجاع قائمة المرؤوسين بنجاح",
    "data": [
        {
            "user_id": 125,
            "name": "علي أحمد",
            "department": "قسم تقنية المعلومات",
            "designation": "مطور مبتدئ",
            "hierarchy_level": 2
        }
    ]
}
```

## رموز الأخطاء

### أخطاء المصادقة
- `401 Unauthorized`: المستخدم غير مصادق عليه
- `403 Forbidden`: المستخدم لا يملك الصلاحية المطلوبة

### أخطاء التحقق من البيانات
- `422 Unprocessable Entity`: بيانات غير صحيحة

```json
{
    "success": false,
    "message": "البيانات المدخلة غير صحيحة",
    "errors": {
        "email": ["البريد الإلكتروني مطلوب"],
        "first_name": ["الاسم الأول مطلوب"]
    }
}
```

### أخطاء الموارد
- `404 Not Found`: الموظف غير موجود

```json
{
    "success": false,
    "message": "الموظف غير موجود"
}
```

### أخطاء الخادم
- `500 Internal Server Error`: خطأ في الخادم

```json
{
    "success": false,
    "message": "حدث خطأ في الخادم"
}
```

## أمثلة على الاستخدام

### إنشاء موظف جديد باستخدام cURL

```bash
curl -X POST \
  https://api.example.com/api/employees \
  -H 'Authorization: Bearer your_access_token' \
  -H 'Content-Type: application/json' \
  -d '{
    "first_name": "محمد",
    "last_name": "علي",
    "email": "mohamed.ali@company.com",
    "password": "SecurePass123",
    "user_type": "staff",
    "department_id": 5,
    "designation_id": 10,
    "basic_salary": 5000.00
  }'
```

### البحث عن موظف باستخدام JavaScript

```javascript
const searchEmployees = async (query) => {
  const response = await fetch(`/api/employees/search?q=${encodeURIComponent(query)}`, {
    headers: {
      'Authorization': `Bearer ${accessToken}`,
      'Content-Type': 'application/json'
    }
  });
  
  const data = await response.json();
  return data;
};
```

## ملاحظات مهمة

1. **الأمان**: جميع كلمات المرور يتم تشفيرها باستخدام bcrypt
2. **عزل الشركات**: كل شركة ترى موظفيها فقط
3. **التحقق من الصلاحيات**: يتم التحقق من الصلاحيات في كل طلب
4. **التصفح**: يتم دعم التصفح في جميع القوائم
5. **البحث**: البحث يدعم النصوص العربية والإنجليزية
6. **الإحصائيات**: تحسب الإحصائيات في الوقت الفعلي

## معدل الطلبات

- الحد الأقصى: 60 طلب في الدقيقة لكل مستخدم
- عند تجاوز الحد: HTTP 429 Too Many Requests

## إصدارات API

الإصدار الحالي: v1
Base URL: `/api/v1` (اختياري، يمكن استخدام `/api` مباشرة)