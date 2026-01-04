# Custody Clearance Module - Implementation Plan
# نظام إخلاء الطرف للعهد

## 1. Overview (نظرة عامة)

### الهدف
إنشاء module جديد لإدارة إخلاء طرف العهد (Custody Clearance) مع 3 endpoints رئيسية:

1. **عرض العهد**: عرض العهد للموظف المسجل دخوله أو موظفيه التابعين
2. **طلب إخلاء الطرف**: إنشاء طلب إخلاء طرف للعهد (كلها أو محددة)
3. **الموافقة/الرفض**: نظام موافقات متعدد المستويات مع إرسال إشعار للموظف المختص بالعهد

---

## 2. Database Schema

### جدول العهد (ci_erp_assets)
| Column | Type | Description |
|--------|------|-------------|
| assets_id | PK | معرف الأصل |
| company_id | FK | معرف الشركة |
| allocated_employee_id | FK | الموظف المستلم للعهدة |
| name | VARCHAR | اسم الأصل |
| serial_number | VARCHAR | الرقم التسلسلي |
| status | ENUM | working/damaged/disposed |

### جدول طلبات الإخلاء (ci_custody_clearance)
| Column | Type | Description |
|--------|------|-------------|
| clearance_id | PK | معرف الطلب |
| company_id | FK | معرف الشركة |
| employee_id | FK | الموظف طالب الإخلاء |
| clearance_date | DATE | تاريخ الإخلاء |
| clearance_type | ENUM | resignation/termination/transfer/other |
| status | ENUM | pending/approved/rejected |
| approved_by | FK | المعتمد |
| created_by | FK | منشئ الطلب |

---

## 3. Files Structure

```
app/
├── DTOs/CustodyClearance/
│   ├── CustodyFilterDTO.php ✅
│   ├── CustodyClearanceFilterDTO.php ✅
│   ├── CreateCustodyClearanceDTO.php ✅
│   ├── ApproveCustodyClearanceDTO.php ✅
│   ├── CustodyResponseDTO.php ✅
│   └── CustodyClearanceResponseDTO.php ✅
├── Http/
│   ├── Controllers/Api/
│   │   └── CustodyClearanceController.php ✅
│   └── Requests/CustodyClearance/
│       ├── GetCustodiesRequest.php ✅
│       ├── CreateCustodyClearanceRequest.php ✅
│       └── ApproveCustodyClearanceRequest.php ✅
├── Repository/
│   ├── Interface/
│   │   └── CustodyClearanceRepositoryInterface.php ✅
│   └── CustodyClearanceRepository.php ✅
├── Services/
│   └── CustodyClearanceService.php ✅
├── Mail/CustodyClearance/
│   ├── CustodyClearanceSubmitted.php ✅
│   ├── CustodyClearanceApproved.php ✅
│   └── CustodyClearanceRejected.php ✅
└── Providers/
    └── AppServiceProvider.php ✅ (binding added)

routes/
└── api.php ✅ (routes added)
```

---

## 4. API Endpoints

### 4.1 GET /api/custodies
عرض العهد للموظف أو تابعيه

**Query Parameters:**
- `employee_id`: int (optional)
- `search`: string (optional)
- `status`: working|damaged|disposed

### 4.2 GET /api/custody-clearances
عرض طلبات الإخلاء

### 4.3 POST /api/custody-clearances
إنشاء طلب إخلاء جديد

**Body:**
```json
{
  "employee_id": 767,
  "clearance_date": "2026-01-15",
  "clearance_type": "resignation",
  "asset_ids": [1, 2, 3],
  "notes": "ملاحظات"
}
```

### 4.4 GET /api/custody-clearances/{id}
عرض تفاصيل طلب

### 4.5 POST /api/custody-clearances/{id}/approve-or-reject
موافقة/رفض

**Body:**
```json
{
  "action": "approve",
  "remarks": "تمت الموافقة"
}
```

---

## 5. Approval Workflow

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│   الموظف    │────▶│  المستوى 1   │────▶│  المستوى 2   │
│  يقدم طلب  │     │    يوافق     │     │    يوافق     │
└─────────────┘     └──────────────┘     └──────────────┘
                           │                    │
                           ▼                    ▼
                    ┌──────────────┐     ┌──────────────┐
                    │    رفض      │     │ موافقة نهائية │
                    │ ينتهي الطلب │     │ إشعار للمسؤول │
                    └──────────────┘     └──────────────┘
```

- **module_option**: `custody_clearance_settings`
- يتبع نفس سياسة `ApprovalService` الموجودة
- عند الموافقة النهائية: يُرسل تنبيه للموظف المختص بالعهد (via notify field)

---

## 6. Email Notifications

| Event | Email Class | Recipients |
|-------|-------------|------------|
| تقديم الطلب | `CustodyClearanceSubmitted` | المعتمد الأول |
| الموافقة | `CustodyClearanceApproved` | الموظف + المسؤول |
| الرفض | `CustodyClearanceRejected` | الموظف |

---

## 7. Dependencies

- `ApprovalService` - إدارة الموافقات المتعددة
- `NotificationService` - إرسال الإشعارات
- `SimplePermissionService` - فحص الصلاحيات
- `Asset` model - العهد
- `CustodyClearance` model - موجود
- `CustodyClearanceItem` model - موجود
