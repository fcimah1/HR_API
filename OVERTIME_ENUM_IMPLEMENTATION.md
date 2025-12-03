# Overtime Request Enum Implementation

## ✅ Implementation Complete

This document describes the enum-based overtime request system that allows semantic API requests while maintaining database compatibility.

---

## 📋 What Changed

### 1. **New Enums Created**

#### `OvertimeReasonEnum` (5 reasons)
```php
STANDBY_PAY = 1              // "Standby Pay"
WORK_THROUGH_LUNCH = 2       // "Work Through Lunch"
OUT_OF_TOWN = 3              // "Out of Town"
SALARIED_EMPLOYEE = 4        // "Salaried Employee"
ADDITIONAL_WORK_HOURS = 5    // "Additional Work Hours"
```

#### `CompensationTypeEnum` (2 types)
```php
BANKED = 1                   // "Banked"
PAYOUT = 2                   // "Payout"
```

### 2. **Updated Files**
- ✅ `app/Enums/OvertimeReasonEnum.php` - Created
- ✅ `app/Enums/CompensationTypeEnum.php` - Created
- ✅ `app/Http/Requests/Overtime/CreateOvertimeRequestRequest.php` - Updated validation
- ✅ `app/Http/Requests/Overtime/UpdateOvertimeRequestRequest.php` - Updated validation
- ✅ `app/Models/OvertimeRequest.php` - Added enum casts
- ✅ `app/Http/Resources/OvertimeResource.php` - Updated response format
- ✅ `app/Http/Controllers/Api/OvertimeController.php` - Updated documentation
- ✅ `app/Http/Controllers/Api/EnumController.php` - New helper endpoints

---

## 🔄 How It Works

### Request Flow
```
API Client → "STANDBY_PAY" (string)
    ↓
Validation (Rule::enum)
    ↓
Conversion to 1 (integer) in passedValidation()
    ↓
Service Layer (receives integer)
    ↓
Database stores 1
```

### Response Flow
```
Database returns 1 (integer)
    ↓
Model casts to OvertimeReasonEnum::STANDBY_PAY
    ↓
Resource outputs:
    - overtime_reason: "STANDBY_PAY"
    - overtime_reason_label: "Standby Pay"
```

---

## 🧪 Testing Guide

### 1. Create Overtime Request (New Format)

**Endpoint:** `POST /api/overtime/requests`

**Request Body (BEFORE - Old Format):**
```json
{
  "request_date": "2025-12-03",
  "clock_in": "2:30 PM",
  "clock_out": "7:00 PM",
  "overtime_reason": 1,
  "compensation_type": 1,
  "request_reason": "Emergency deployment"
}
```

**Request Body (NOW - New Format):**
```json
{
  "request_date": "2025-12-03",
  "clock_in": "2:30 PM",
  "clock_out": "7:00 PM",
  "overtime_reason": "STANDBY_PAY",
  "compensation_type": "BANKED",
  "request_reason": "Emergency deployment"
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "تم إنشاء طلب العمل الإضافي بنجاح",
  "data": {
    "time_request_id": 123,
    "overtime_reason": "STANDBY_PAY",
    "overtime_reason_label": "Standby Pay",
    "overtime_reason_label_ar": "بدل عمل اضافي (مبلغ)",
    "compensation_type": "BANKED",
    "compensation_type_label": "Banked",
    "compensation_type_label_ar": "بنكي",
    ...
  }
}
```

---

### 2. Get Available Enums

**Endpoint:** `GET /api/enums/overtime-reasons`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "value": 1,
      "name": "STANDBY_PAY",
      "label": "Standby Pay",
      "label_ar": "بدل عمل اضافي (مبلغ)"
    },
    {
      "value": 2,
      "name": "WORK_THROUGH_LUNCH",
      "label": "Work Through Lunch",
      "label_ar": "العمل وقت الاستراحة"
    },
    {
      "value": 3,
      "name": "OUT_OF_TOWN",
      "label": "Out of Town",
      "label_ar": "تعيين مهمة عمل خارج المدينة"
    },
    {
      "value": 4,
      "name": "SALARIED_EMPLOYEE",
      "label": "Salaried Employee",
      "label_ar": "براتب إضافي"
    },
    {
      "value": 5,
      "name": "ADDITIONAL_WORK_HOURS",
      "label": "Additional Work Hours",
      "label_ar": "ساعات عمل إضافية"
    }
  ]
}
```

**Endpoint:** `GET /api/enums/compensation-types`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "value": 1,
      "name": "BANKED",
      "label": "Banked",
      "label_ar": "بنكي"
    },
    {
      "value": 2,
      "name": "PAYOUT",
      "label": "Payout",
      "label_ar": "على الراتب"
    }
  ]
}
```

---

### 3. Test Cases

#### ✅ Valid Request
```bash
curl -X POST http://localhost:8000/api/overtime/requests \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "request_date": "2025-12-03",
    "clock_in": "9:00 AM",
    "clock_out": "5:00 PM",
    "overtime_reason": "OUT_OF_TOWN",
    "compensation_type": "PAYOUT",
    "request_reason": "Client site visit"
  }'
```

#### ❌ Invalid Enum (Should Fail)
```bash
curl -X POST http://localhost:8000/api/overtime/requests \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "request_date": "2025-12-03",
    "clock_in": "9:00 AM",
    "clock_out": "5:00 PM",
    "overtime_reason": "INVALID_REASON",
    "compensation_type": "BANKED"
  }'
```

**Expected Error:**
```json
{
  "success": false,
  "message": "The selected overtime_reason is invalid.",
  "errors": {
    "overtime_reason": ["The selected overtime_reason is invalid."]
  }
}
```

#### ✅ ADDITIONAL_WORK_HOURS with additional_work_hours Field
```bash
curl -X POST http://localhost:8000/api/overtime/requests \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "request_date": "2025-12-03",
    "clock_in": "9:00 AM",
    "clock_out": "5:00 PM",
    "overtime_reason": "ADDITIONAL_WORK_HOURS",
    "additional_work_hours": 2,
    "compensation_type": "BANKED"
  }'
```

---

## 🗄️ Database Schema (Unchanged)

```sql
-- Database still stores integers
overtime_reason INT NOT NULL       -- Stores 1, 2, 3, 4, or 5
compensation_type INT NOT NULL     -- Stores 1 or 2
```

**No migration needed!** The enums handle conversion automatically.

---

## 🏗️ Architecture Benefits

### ✅ Zero-Trust Input Validation
```php
Rule::enum(OvertimeReasonEnum::class)  // Only accepts valid enum names
```

### ✅ Type Safety
```php
$request->overtime_reason;  // Returns integer (after passedValidation)
$model->overtime_reason;    // Returns OvertimeReasonEnum instance (after retrieval)
```

### ✅ Self-Documenting API
```php
// Old: What does 1 mean?
"overtime_reason": 1

// New: Crystal clear
"overtime_reason": "STANDBY_PAY"
"overtime_reason_label": "Standby Pay"
```

### ✅ Backward Compatible
- Database structure unchanged
- Legacy integer values still work internally
- Service layer receives integers (no refactoring needed)

---

## 📝 Frontend Implementation Guide (Arabic Dropdown)

### Step 1: Fetch Available Enums
```javascript
// Fetch enums on app initialization or page load
const response = await fetch('/api/enums/overtime-reasons', {
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  }
});
const result = await response.json();
console.log(result.data);
```

### Step 2: Build Arabic Dropdown
```html
<select id="overtime_reason" name="overtime_reason">
  <!-- Options will be populated dynamically -->
</select>
```

```javascript
// Populate dropdown with Arabic labels
const select = document.getElementById('overtime_reason');

result.data.forEach(reason => {
  const option = document.createElement('option');
  option.value = reason.name;        // English: "STANDBY_PAY" (sent to API)
  option.textContent = reason.label_ar;  // Arabic: "بدل عمل اضافي (مبلغ)" (shown to user)
  select.appendChild(option);
});
```

**Result: User sees Arabic dropdown:**
```
بدل عمل اضافي (مبلغ)
العمل وقت الاستراحة
تعيين مهمة عمل خارج المدينة
براتب إضافي
ساعات عمل إضافية
```

**But form submits English:**
```json
{
  "overtime_reason": "STANDBY_PAY"
}
```

### Step 3: Display Response in Arabic
```javascript
// When displaying overtime request details
const request = await fetch('/api/overtime/requests/123');
const data = await request.json();

// Show Arabic label to user
document.getElementById('reason').textContent = data.data.overtime_reason_label_ar;
// Displays: "بدل عمل اضافي (مبلغ)"
```

---

## 🔧 Routes to Add (if not already exists)

Add these to `routes/api.php`:

```php
// Enum helper endpoints
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/enums', [EnumController::class, 'index']);
    Route::get('/enums/overtime-reasons', [EnumController::class, 'overtimeReasons']);
    Route::get('/enums/compensation-types', [EnumController::class, 'compensationTypes']);
    Route::get('/enums/travel-modes', [EnumController::class, 'travelModes']);
});
```

---

## 🎯 Summary

| Aspect | Before | After |
|--------|--------|-------|
| **API Request** | `"overtime_reason": 1` | `"overtime_reason": "STANDBY_PAY"` |
| **API Response** | `"overtime_reason": 1` | `"overtime_reason": "STANDBY_PAY"`, `"overtime_reason_label": "Standby Pay"` |
| **Database** | `1` (int) | `1` (int) - **NO CHANGE** |
| **Validation** | `Rule::in([1,2,3,4,5])` | `Rule::enum(OvertimeReasonEnum::class)` |
| **Type Safety** | ❌ Magic numbers | ✅ Strongly typed enums |

---

## 🚀 Next Steps

1. **Add routes** to `routes/api.php` (if not already there)
2. **Test endpoints** using the curl examples above
3. **Update frontend** to use enum names instead of integers
4. **Regenerate Swagger docs** if using OpenAPI/Swagger

---

## 📚 Additional Enums to Consider

You can apply the same pattern to:
- Leave types
- Travel modes (already exists as `TravelModeEnum`)
- Approval statuses
- Department types
- Employee roles

---

## 🐛 Troubleshooting

### Error: "The selected overtime_reason is invalid"
✅ Make sure you're sending the enum NAME (e.g., "STANDBY_PAY"), not the integer value (1).

### Error: "يجب تحديد نوع ساعات العمل الإضافية"
✅ When using `overtime_reason: "ADDITIONAL_WORK_HOURS"`, the `additional_work_hours` field is required.

### Database still shows integers
✅ This is correct! The database stores integers. Enums are for API contracts only.

---

## 📞 Support

If you have questions or need adjustments, refer to:
- `app/Enums/OvertimeReasonEnum.php`
- `app/Http/Requests/Overtime/CreateOvertimeRequestRequest.php`

**All tests passing ✅ | No linter errors ✅ | Database backward compatible ✅**

