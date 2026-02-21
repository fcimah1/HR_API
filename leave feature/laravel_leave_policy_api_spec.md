# Country-Based Leave Policy Feature - Laravel API Implementation Guide

## Overview

This document provides comprehensive specifications for implementing a **Country-Based Leave Policy** system in a Laravel API project. The feature calculates employee leave entitlements based on the labor laws of their company's selected country (Saudi Arabia, Egypt, Kuwait, or Qatar).

> [!IMPORTANT]
> This implementation uses the **existing `sfessa_hr` database**. The required tables already exist - do NOT create new migrations for the core tables.

---

## Database Schema

### Existing Tables (Already in sfessa_hr.sql)

#### 1. `ci_leave_policy_countries`

Stores leave policy rules per country and leave type.

```sql
CREATE TABLE `ci_leave_policy_countries` (
  `policy_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL DEFAULT 0,  -- 0 = System Default
  `country_code` varchar(5) NOT NULL,       -- SA, EG, KW, QA
  `leave_type` varchar(50) NOT NULL,        -- annual, sick, maternity, hajj, emergency
  `tier_order` int(11) NOT NULL DEFAULT 1,
  `service_years_min` float NOT NULL DEFAULT 0,
  `service_years_max` float DEFAULT NULL,
  `entitlement_days` int(11) NOT NULL DEFAULT 0,
  `is_paid` tinyint(1) NOT NULL DEFAULT 1,
  `payment_percentage` int(11) NOT NULL DEFAULT 100,
  `is_one_time` tinyint(1) NOT NULL DEFAULT 0,
  `requires_documentation` tinyint(1) NOT NULL DEFAULT 0,
  `policy_description_en` text DEFAULT NULL,
  `policy_description_ar` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`policy_id`)
);
```

#### 2. `ci_erp_company_settings`

Contains `leave_policy_country` column (varchar(10)) - stores selected country code.

#### 3. `ci_erp_users_details`

Contains `is_disability` column (tinyint(1)) - for Egypt's disability entitlement.

#### 4. `ci_erp_users`

Employee records with `date_of_joining` for service years calculation.

---

## Leave Policy Rules by Country

### Saudi Arabia (SA) - نظام العمل السعودي

| Leave Type    | Service Years | Entitlement   | Payment % | Notes                      |
| ------------- | ------------- | ------------- | --------- | -------------------------- |
| **Annual**    | < 1 year      | 0 days        | 100%      | Not entitled before 1 year |
| **Annual**    | 1-5 years     | 21 days       | 100%      | Fully paid                 |
| **Annual**    | 5+ years      | 30 days       | 100%      | Fully paid                 |
| **Sick**      | Any           | First 30 days | 100%      | Fully paid                 |
| **Sick**      | Any           | Days 31-90    | 75%       | 25% salary deduction       |
| **Sick**      | Any           | Days 91-120   | 0%        | Unpaid                     |
| **Maternity** | Any           | 70 days       | 100%      | Fully paid                 |
| **Maternity** | Any           | +60 days      | 0%        | Optional unpaid extension  |
| **Hajj**      | 2+ years      | 10-15 days    | 100%      | ONE TIME only              |
| **Emergency** | Any           | 5 days        | 100%      | Spouse/Parent/Child death  |
| **Emergency** | Any           | 3 days        | 100%      | Sibling death              |
| **Emergency** | Any           | 3 days        | 100%      | Paternity (new baby)       |

---

### Egypt (EG) - قانون العمل المصري

| Leave Type    | Service Years | Entitlement   | Payment % | Notes                          |
| ------------- | ------------- | ------------- | --------- | ------------------------------ |
| **Annual**    | < 6 months    | 0 days        | 100%      | Not entitled                   |
| **Annual**    | 6m - 1 year   | 15 days       | 100%      | Fully paid                     |
| **Annual**    | 1-10 years    | 21 days       | 100%      | Fully paid                     |
| **Annual**    | 10+ years     | 30 days       | 100%      | 45 days if `is_disability=1`   |
| **Sick**      | Any           | First 90 days | 100%      | Full salary + allowances       |
| **Sick**      | Any           | Days 91-365   | 0%        | Unpaid (up to 12 months total) |
| **Maternity** | Any           | 120 days      | 100%      | 4 months fully paid            |
| **Hajj**      | 5+ years      | 30 days       | 100%      | ONE TIME only                  |
| **Emergency** | Any           | 3 days        | 100%      | Fully paid                     |

---

### Kuwait (KW) - قانون العمل الكويتي

| Leave Type    | Service Years | Entitlement  | Payment % | Notes                               |
| ------------- | ------------- | ------------ | --------- | ----------------------------------- |
| **Annual**    | < 9 months    | 0 days       | 100%      | Not entitled                        |
| **Annual**    | 9+ months     | 30 days      | 100%      | Fully paid                          |
| **Sick**      | Any           | 75 days/year | 100%      | Fully paid with medical certificate |
| **Maternity** | Any           | 70 days      | 100%      | Fully paid                          |
| **Maternity** | Any           | +120 days    | 0%        | Optional unpaid extension           |
| **Hajj**      | 2+ years      | 21 days      | 100%      | ONE TIME only                       |
| **Emergency** | Any           | 3 days       | 100%      | General emergency                   |
| **Emergency** | Female        | 130 days     | 100%      | Husband death (Iddah period)        |

---

### Qatar (QA) - قانون العمل القطري

| Leave Type    | Service Years | Entitlement   | Payment % | Notes              |
| ------------- | ------------- | ------------- | --------- | ------------------ |
| **Annual**    | < 5 years     | 21 days       | 100%      | 3 weeks            |
| **Annual**    | 5+ years      | 28 days       | 100%      | 4 weeks            |
| **Sick**      | 3+ months     | First 14 days | 100%      | 2 weeks fully paid |
| **Sick**      | 3+ months     | Days 15-42    | 50%       | 4 weeks half paid  |
| **Sick**      | 3+ months     | Days 43-84    | 0%        | 6 weeks unpaid     |
| **Maternity** | 1+ year       | 50 days       | 100%      | Fully paid         |

> [!NOTE]
> Qatar does NOT have explicit Hajj or Emergency leave policies.

---

## API Endpoints

### 1. Get Company Leave Policy Configuration

```
GET /api/v1/company/leave-policy
```

**Response:**

```json
{
  "success": true,
  "data": {
    "country_code": "SA",
    "country_name_en": "Saudi Arabia",
    "country_name_ar": "المملكة العربية السعودية",
    "policies": [
      {
        "leave_type": "annual",
        "leave_type_ar": "الإجازة السنوية",
        "tiers": [
          {
            "service_years_min": 0,
            "service_years_max": 1,
            "entitlement_days": 0,
            "payment_percentage": 100,
            "description_en": "No entitlement before 1 year",
            "description_ar": "لا يستحق إجازة قبل سنة"
          }
        ]
      }
    ]
  }
}
```

---

### 2. Update Company Leave Policy Country

```
POST /api/v1/company/leave-policy
```

**Request Body:**

```json
{
  "country_code": "SA"
}
```

**Validation:**

- `country_code` must be one of: `SA`, `EG`, `KW`, `QA`

---

### 3. Get Employee Leave Entitlement

```
GET /api/v1/employee/{employee_id}/leave-entitlement
```

**Response:**

```json
{
  "success": true,
  "data": {
    "employee_id": 123,
    "service_years": 3.5,
    "country_code": "SA",
    "is_disability": false,
    "entitlements": {
      "annual": {
        "entitled_days": 21,
        "used_days": 5,
        "pending_days": 2,
        "remaining_days": 14,
        "payment_percentage": 100
      },
      "sick": {
        "tiers": [
          { "days": 30, "payment_percentage": 100 },
          { "days": 60, "payment_percentage": 75 },
          { "days": 30, "payment_percentage": 0 }
        ]
      }
    }
  }
}
```

---

### 4. Calculate Leave Application Entitlement

```
POST /api/v1/leave/calculate
```

**Request Body:**

```json
{
  "employee_id": 123,
  "leave_type": "annual",
  "start_date": "2026-02-01",
  "end_date": "2026-02-10"
}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "requested_days": 10,
    "entitled_days": 21,
    "remaining_after": 11,
    "payment_percentage": 100,
    "is_eligible": true,
    "policy_applied": {
      "policy_id": 5,
      "description_en": "21 days for 1-5 years service",
      "description_ar": "21 يوماً للخدمة من 1-5 سنوات"
    }
  }
}
```

---

## Laravel Implementation

### Models

#### 1. `app/Models/LeaveCountryPolicy.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveCountryPolicy extends Model
{
    protected $table = 'ci_leave_policy_countries';
    protected $primaryKey = 'policy_id';
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'country_code', 'leave_type', 'tier_order',
        'service_years_min', 'service_years_max', 'entitlement_days',
        'is_paid', 'payment_percentage', 'is_one_time',
        'requires_documentation', 'policy_description_en',
        'policy_description_ar', 'is_active'
    ];

    public function scopeForCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode)
                     ->where('is_active', 1)
                     ->orderBy('leave_type')
                     ->orderBy('tier_order');
    }

    public function scopeSystemDefaults($query)
    {
        return $query->where('company_id', 0);
    }
}
```

#### 2. `app/Models/CompanySetting.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $table = 'ci_erp_company_settings';
    protected $primaryKey = 'setting_id';
    public $timestamps = false;

    protected $fillable = ['company_id', 'leave_policy_country'];

    public function getLeavePolicyCountry()
    {
        return $this->leave_policy_country;
    }
}
```

---

### Services

#### `app/Services/LeavePolicyService.php`

```php
<?php

namespace App\Services;

use App\Models\LeaveCountryPolicy;
use App\Models\CompanySetting;
use App\Models\Employee;
use Carbon\Carbon;

class LeavePolicyService
{
    /**
     * Get all policies for a country grouped by leave type
     */
    public function getPoliciesForCountry(string $countryCode): array
    {
        $policies = LeaveCountryPolicy::systemDefaults()
            ->forCountry($countryCode)
            ->get()
            ->groupBy('leave_type');

        return $policies->toArray();
    }

    /**
     * Calculate employee service years
     */
    public function calculateServiceYears(int $employeeId): float
    {
        $employee = Employee::find($employeeId);
        $joinDate = Carbon::parse($employee->date_of_joining);
        return $joinDate->diffInYears(now()) + ($joinDate->diffInMonths(now()) % 12) / 12;
    }

    /**
     * Get applicable policy for employee and leave type
     */
    public function getApplicablePolicy(
        int $employeeId,
        string $leaveType,
        string $countryCode
    ): ?LeaveCountryPolicy {
        $serviceYears = $this->calculateServiceYears($employeeId);
        $employee = Employee::with('details')->find($employeeId);

        // Special handling for Egypt disability
        if ($countryCode === 'EG' && $leaveType === 'annual') {
            if ($employee->details->is_disability && $serviceYears >= 10) {
                // Return 45 days entitlement for disabled employees
                return $this->getDisabilityPolicy($countryCode);
            }
        }

        return LeaveCountryPolicy::systemDefaults()
            ->forCountry($countryCode)
            ->where('leave_type', $leaveType)
            ->where('service_years_min', '<=', $serviceYears)
            ->where(function($q) use ($serviceYears) {
                $q->whereNull('service_years_max')
                  ->orWhere('service_years_max', '>', $serviceYears);
            })
            ->orderBy('tier_order')
            ->first();
    }

    /**
     * Validate if employee can take leave
     */
    public function validateLeaveRequest(
        int $employeeId,
        string $leaveType,
        int $requestedDays
    ): array {
        $companySettings = CompanySetting::where('company_id',
            Employee::find($employeeId)->company_id
        )->first();

        $countryCode = $companySettings->leave_policy_country;
        $policy = $this->getApplicablePolicy($employeeId, $leaveType, $countryCode);

        if (!$policy) {
            return [
                'is_eligible' => false,
                'message' => 'No applicable policy found'
            ];
        }

        if ($policy->entitlement_days == 0) {
            return [
                'is_eligible' => false,
                'message' => 'Employee does not meet service requirements'
            ];
        }

        // Check one-time leaves (Hajj)
        if ($policy->is_one_time) {
            $previouslyTaken = $this->hasUsedOneTimeLeave($employeeId, $leaveType);
            if ($previouslyTaken) {
                return [
                    'is_eligible' => false,
                    'message' => 'This leave type can only be taken once'
                ];
            }
        }

        return [
            'is_eligible' => true,
            'entitled_days' => $policy->entitlement_days,
            'payment_percentage' => $policy->payment_percentage,
            'policy_id' => $policy->policy_id
        ];
    }
}
```

---

### Controllers

#### `app/Http/Controllers/Api/V1/LeavePolicyController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LeavePolicyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeavePolicyController extends Controller
{
    protected LeavePolicyService $policyService;

    public function __construct(LeavePolicyService $policyService)
    {
        $this->policyService = $policyService;
    }

    /**
     * GET /api/v1/company/leave-policy
     */
    public function getCompanyPolicy(Request $request): JsonResponse
    {
        $companyId = auth()->user()->company_id;
        $settings = CompanySetting::where('company_id', $companyId)->first();

        if (!$settings || !$settings->leave_policy_country) {
            return response()->json([
                'success' => false,
                'message' => 'No leave policy configured'
            ], 404);
        }

        $countryCode = $settings->leave_policy_country;
        $policies = $this->policyService->getPoliciesForCountry($countryCode);

        return response()->json([
            'success' => true,
            'data' => [
                'country_code' => $countryCode,
                'policies' => $policies
            ]
        ]);
    }

    /**
     * POST /api/v1/company/leave-policy
     */
    public function updateCompanyPolicy(Request $request): JsonResponse
    {
        $request->validate([
            'country_code' => 'required|in:SA,EG,KW,QA'
        ]);

        $companyId = auth()->user()->company_id;

        CompanySetting::updateOrCreate(
            ['company_id' => $companyId],
            ['leave_policy_country' => $request->country_code]
        );

        return response()->json([
            'success' => true,
            'message' => 'Leave policy updated successfully'
        ]);
    }

    /**
     * GET /api/v1/employee/{id}/leave-entitlement
     */
    public function getEmployeeEntitlement(int $employeeId): JsonResponse
    {
        $entitlements = $this->policyService->calculateAllEntitlements($employeeId);

        return response()->json([
            'success' => true,
            'data' => $entitlements
        ]);
    }
}
```

---

### Routes

#### `routes/api.php`

```php
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Company Leave Policy
    Route::get('company/leave-policy', [LeavePolicyController::class, 'getCompanyPolicy']);
    Route::post('company/leave-policy', [LeavePolicyController::class, 'updateCompanyPolicy']);

    // Employee Entitlements
    Route::get('employee/{id}/leave-entitlement', [LeavePolicyController::class, 'getEmployeeEntitlement']);

    // Leave Calculation
    Route::post('leave/calculate', [LeavePolicyController::class, 'calculateLeave']);
});
```

---

## Country Names Reference

| Code | English      | Arabic                   |
| ---- | ------------ | ------------------------ |
| SA   | Saudi Arabia | المملكة العربية السعودية |
| EG   | Egypt        | جمهورية مصر العربية      |
| KW   | Kuwait       | دولة الكويت              |
| QA   | Qatar        | دولة قطر                 |

---

## Leave Type Names Reference

| Key       | English               | Arabic                |
| --------- | --------------------- | --------------------- |
| annual    | Annual Leave          | الإجازة السنوية       |
| sick      | Sick Leave            | الإجازة المرضية       |
| maternity | Maternity Leave       | إجازة الأمومة         |
| hajj      | Hajj Leave            | إجازة الحج            |
| emergency | Emergency/Death Leave | إجازة الوفاة والطوارئ |

---

## Implementation Checklist

- [ ] Create Laravel Models for existing database tables
- [ ] Create LeavePolicyService with business logic
- [ ] Create API Controllers
- [ ] Define API routes
- [ ] Add request validation
- [ ] Add API authentication middleware
- [ ] Test all endpoints
- [ ] Add API documentation (Swagger/OpenAPI)

---

## Important Notes

1. **Database**: Use the existing `sfessa_hr` database - tables are already created
2. **Egypt Disability**: Check `ci_erp_users_details.is_disability` for 45-day entitlement
3. **One-Time Leaves**: Hajj can only be taken once per employee
4. **Tiered Sick Leave**: SA and QA have multiple tiers with different payment percentages
5. **Service Years**: Calculate from `date_of_joining` in `ci_erp_users` table
