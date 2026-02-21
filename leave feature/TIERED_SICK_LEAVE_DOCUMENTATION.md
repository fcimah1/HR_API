# Saudi Arabia Tiered Sick Leave - Technical Documentation

## Overview

This document provides comprehensive technical documentation for the Saudi Arabia (SA) tiered sick leave feature, including automatic salary deductions based on cumulative sick days used per year.

---

## Policy Summary

### Saudi Arabia Labor Law - Sick Leave

| Tier | Cumulative Days | Payment | Deduction | Arabic Label               |
| ---- | --------------- | ------- | --------- | -------------------------- |
| 1    | Days 1-30       | 100%    | 0%        | إجازة مرضية مدفوعة بالكامل |
| 2    | Days 31-90      | 75%     | 25%       | خصم إجازة مرضية (25%)      |
| 3    | Days 91-120     | 0%      | 100%      | خصم إجازة مرضية (100%)     |

**Daily Rate Calculation:**

```
daily_rate = basic_salary / 30
```

**Deduction Calculation:**

```
deduction_amount = days_in_tier × daily_rate × (deduction_percentage / 100)
```

---

## Database Schema

### New Table: `ci_sick_leave_deductions`

This table stores salary deduction records for tiered sick leave.

```sql
CREATE TABLE IF NOT EXISTS `ci_sick_leave_deductions` (
  `deduction_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_application_id` int(11) NOT NULL COMMENT 'Reference to ci_leave_applications',
  `salary_month` varchar(10) NOT NULL COMMENT 'YYYY-MM format for payroll month',
  `tier_order` int(11) NOT NULL COMMENT '1=Days 1-30, 2=Days 31-90, 3=Days 91-120',
  `days_in_tier` decimal(5,2) NOT NULL COMMENT 'Number of sick days falling in this tier',
  `daily_rate` decimal(15,2) NOT NULL COMMENT 'basic_salary / 30',
  `deduction_percentage` int(11) NOT NULL COMMENT '25 for tier 2, 100 for tier 3',
  `deduction_amount` decimal(15,2) NOT NULL COMMENT 'Calculated deduction amount',
  `pay_title` varchar(200) NOT NULL COMMENT 'Deduction label for payslip',
  `is_processed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Added to payslip',
  `payslip_id` int(11) DEFAULT NULL COMMENT 'Reference to ci_payslips when processed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`deduction_id`),
  KEY `idx_emp_month` (`employee_id`, `salary_month`),
  KEY `idx_leave_app` (`leave_application_id`),
  KEY `idx_unprocessed` (`is_processed`, `salary_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### Modified Table: `ci_leave_applications`

These columns are used for policy tracking:

| Column                     | Type       | Description                               |
| -------------------------- | ---------- | ----------------------------------------- |
| `tier_order`               | int(11)    | Which tier of sick leave (1, 2, or 3)     |
| `payment_percentage`       | int(11)    | Percentage of salary paid (100, 75, or 0) |
| `salary_deduction_applied` | tinyint(1) | 1 = Deduction records created             |

---

## File Changes

### 1. LeavePolicy.php

**Location:** `app/Libraries/LeavePolicy.php`

#### New Methods Added

##### `getCumulativeSickDaysUsed($employeeId, $year, $leaveTypeId = null)`

Gets the total approved sick days used by an employee in a specific year.

```php
/**
 * @param int $employeeId
 * @param int $year
 * @param string|null $leaveTypeId Optional: specific leave type ID
 * @return float Total sick days used (approved)
 */
public function getCumulativeSickDaysUsed($employeeId, $year, $leaveTypeId = null)
```

**Example:**

```php
$LeavePolicy = new \App\Libraries\LeavePolicy();
$cumulativeDays = $LeavePolicy->getCumulativeSickDaysUsed(679, 2026);
// Returns: 25.0 (if employee has used 25 sick days this year)
```

---

##### `calculateTierSplit($cumulativeDays, $requestedDays, $countryCode)`

Splits a sick leave request across tier boundaries.

```php
/**
 * @param float $cumulativeDays Days already used this year
 * @param float $requestedDays Days being requested
 * @param string $countryCode SA, QA, etc.
 * @return array Array of tier segments
 */
public function calculateTierSplit($cumulativeDays, $requestedDays, $countryCode)
```

**Example:**

```php
$segments = $LeavePolicy->calculateTierSplit(25, 15, 'SA');
// Returns:
// [
//   ['tier_order' => 1, 'days' => 5, 'payment_percentage' => 100, 'deduction_percentage' => 0],
//   ['tier_order' => 2, 'days' => 10, 'payment_percentage' => 75, 'deduction_percentage' => 25]
// ]
```

**Explanation:** Employee has used 25 days, requests 15 more:

- Days 26-30 = 5 days in Tier 1 (100% paid)
- Days 31-40 = 10 days in Tier 2 (75% paid, 25% deduction)

---

##### `createSickLeaveDeductions($leaveApplicationId)`

Creates salary deduction records when tiered sick leave is approved.

```php
/**
 * Called when a tiered sick leave is approved
 * @param int $leaveApplicationId
 * @return bool Success
 */
public function createSickLeaveDeductions($leaveApplicationId)
```

**What it does:**

1. Gets leave application details
2. Skips if `payment_percentage >= 100` (fully paid)
3. Gets employee's basic salary and calculates daily rate
4. Breaks down leave days by month
5. Creates deduction records in `ci_sick_leave_deductions`
6. Marks `salary_deduction_applied = 1`

---

##### `getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth)`

Gets pending deductions for payroll processing.

```php
/**
 * @param int $employeeId
 * @param string $salaryMonth YYYY-MM format
 * @return array
 */
public function getSickLeaveDeductionsForPayroll($employeeId, $salaryMonth)
```

**Example:**

```php
$deductions = $LeavePolicy->getSickLeaveDeductionsForPayroll(679, '2026-02');
// Returns:
// [
//   [
//     'deduction_id' => 1,
//     'salary_month' => '2026-02',
//     'tier_order' => 2,
//     'days_in_tier' => 10.00,
//     'daily_rate' => 300.00,
//     'deduction_percentage' => 25,
//     'deduction_amount' => 750.00,
//     'pay_title' => 'خصم إجازة مرضية (25%)',
//     'is_processed' => 0
//   ]
// ]
```

---

##### `markSickLeaveDeductionsProcessed($deductionIds, $payslipId)`

Marks deductions as processed after payslip generation.

```php
/**
 * @param array $deductionIds
 * @param int $payslipId
 * @return bool
 */
public function markSickLeaveDeductionsProcessed($deductionIds, $payslipId)
```

---

##### `getTotalSickLeaveQuota($countryCode)`

Gets total sick leave quota for a country (sum of all tiers).

```php
$quota = $LeavePolicy->getTotalSickLeaveQuota('SA');
// Returns: 120 (30 + 60 + 30)
```

---

#### Enhanced Method: `validateLeaveRequest()`

Now calculates and returns `tier_order` and accurate `payment_percentage` for sick leave based on cumulative usage.

**Before:**

```php
return [
    'payment_percentage' => $policy['payment_percentage']  // Always from first tier
];
```

**After:**

```php
// TIERED SICK LEAVE: Calculate tier_order and payment_percentage
if ($systemLeaveType === 'sick') {
    $cumulativeDays = $this->getCumulativeSickDaysUsed($employeeId, $currentYear, $leaveTypeId);
    $tierInfo = $this->getTieredPaymentInfo($countryCode, $systemLeaveType, $cumulativeDays, $requestedDaysActual);
    $tierOrder = $tierInfo['tier_order'];
    $paymentPercentage = $tierInfo['payment_percentage'];
}

return [
    'payment_percentage' => $paymentPercentage,  // Correct tier percentage
    'tier_order' => $tierOrder
];
```

---

### 2. Leave.php Controller

**Location:** `app/Controllers/Erp/Leave.php`

#### Change 1: Store Tier Info (Lines 1504-1507, 1533-1536)

Added `tier_order` to leave insert data arrays:

```php
$data = [
    // ... existing fields ...
    'policy_id' => $policyInfo['policy']['policy_id'] ?? null,
    'payment_percentage' => $policyInfo['payment_percentage'] ?? 100,
    'tier_order' => $policyInfo['tier_order'] ?? 1,  // NEW
];
```

---

#### Change 2: Create Deductions on Approval (Lines 1920-1936)

Added deduction creation when sick leave is approved:

```php
if ($status == 1) { // Approved
    if ($leave_result['policy_id']) {
        $LeavePolicy = new \App\Libraries\LeavePolicy();
        // ... existing one-time leave handling ...

        // TIERED SICK LEAVE: Create salary deductions if applicable
        if ($systemLeaveType === 'sick') {
            $paymentPercentage = $leave_result['payment_percentage'] ?? 100;
            if ($paymentPercentage < 100) {
                $LeavePolicy->createSickLeaveDeductions($id);
            }
        }
    }
}
```

---

#### Change 3: 120-Day Quota for Tiered Sick Leave (Lines 1294-1310)

Modified quota validation to use total sick leave quota (120 days) instead of first tier (30 days):

```php
// TIERED SICK LEAVE: Use total quota (120 days) instead of first tier (30 days)
if ($systemLeaveType === 'sick') {
    $countryCode = $LeavePolicy->getCompanyCountryCode($employee['company_id']);
    $totalSickQuota = $LeavePolicy->getTotalSickLeaveQuota($countryCode);
    if ($totalSickQuota > 0) {
        $days_per_year = $totalSickQuota;  // 120 days for SA
    }
}
```

This allows employees to request sick leave beyond 30 days, with automatic tier and deduction calculation.

---

### 3. payroll_helper.php

**Location:** `app/Helpers/payroll_helper.php`

#### New Functions Added

##### `get_sick_leave_deductions_for_payroll($employee_id, $salary_month)`

```php
/**
 * @param int $employee_id
 * @param string $salary_month Y-m format
 * @return array Array of deduction records
 */
function get_sick_leave_deductions_for_payroll($employee_id, $salary_month)
```

---

##### `mark_sick_leave_deductions_processed($deduction_ids, $payslip_id)`

```php
/**
 * @param array $deduction_ids Array of deduction IDs
 * @param int $payslip_id The generated payslip ID
 * @return bool
 */
function mark_sick_leave_deductions_processed($deduction_ids, $payslip_id)
```

---

##### `calculate_sick_leave_deductions_total($employee_id, $salary_month)`

```php
/**
 * @param int $employee_id
 * @param string $salary_month Y-m format
 * @return array ['total' => float, 'deductions' => array, 'ids' => array]
 */
function calculate_sick_leave_deductions_total($employee_id, $salary_month)
```

---

## Complete Workflow Example

### Scenario

- **Employee:** Ahmed (ID: 679)
- **Basic Salary:** 9,000 SAR/month
- **Daily Rate:** 9,000 / 30 = 300 SAR
- **Previous Sick Days (2026):** 25 days (all in Tier 1)
- **New Request:** 40 days (March 15 to April 23)

---

### Step 1: Leave Request Submission

When Ahmed submits a 40-day sick leave request:

```php
// In add_leave()
$policyInfo = $LeavePolicy->validateLeaveRequest(679, $leave_type_id, $leave_hours);

// policyInfo contains:
// [
//   'payment_percentage' => 75,  // First new tier (Tier 2)
//   'tier_order' => 2,
//   'country_code' => 'SA'
// ]
```

**Tier Calculation:**

- Cumulative: 25 days
- Requested: 40 days
- Days 26-30: 5 days in Tier 1 (100% paid)
- Days 31-65: 35 days in Tier 2 (75% paid)

> **Note:** Current implementation stores the _first applicable tier_ rather than splitting. For a request spanning multiple tiers, you may need to create multiple leave records.

---

### Step 2: Leave Approval

When the leave is approved (status = 1):

```php
// In update_leave_status()
if ($systemLeaveType === 'sick' && $paymentPercentage < 100) {
    $LeavePolicy->createSickLeaveDeductions($leave_id);
}
```

**Deduction Records Created:**

| Month   | Days | Daily Rate | Deduction % | Amount    | Label                 |
| ------- | ---- | ---------- | ----------- | --------- | --------------------- |
| 2026-03 | 17   | 300        | 25%         | 1,275 SAR | خصم إجازة مرضية (25%) |
| 2026-04 | 23   | 300        | 25%         | 1,725 SAR | خصم إجازة مرضية (25%) |

---

### Step 3: Payroll Processing

When generating March 2026 payslip:

```php
// Get sick leave deductions
$sickDeductions = calculate_sick_leave_deductions_total($employee_id, '2026-03');

// Result:
// [
//   'total' => 1275.00,
//   'deductions' => [...],
//   'ids' => [1]
// ]

// Add to payslip
foreach ($sickDeductions['deductions'] as $deduction) {
    $db->table('ci_payslip_statutory_deductions')->insert([
        'payslip_id' => $payslip_id,
        'staff_id' => $employee_id,
        'pay_title' => $deduction['pay_title'],
        'pay_amount' => $deduction['deduction_amount']
    ]);
}

// Mark as processed
mark_sick_leave_deductions_processed($sickDeductions['ids'], $payslip_id);
```

---

### Step 4: Payslip Display

Ahmed's March 2026 payslip shows:

```
Basic Salary:                     9,000 SAR
Housing Allowance:                2,000 SAR
--------------------------------------------
Gross Salary:                    11,000 SAR

Deductions:
- GOSI (9%):                        810 SAR
- خصم إجازة مرضية (25%):          1,275 SAR
--------------------------------------------
Total Deductions:                 2,085 SAR

Net Salary:                       8,915 SAR
```

---

## SQL Queries for Verification

### Check Employee's Cumulative Sick Days

```sql
SELECT
    SUM(la.leave_hours) / 8 AS total_sick_days
FROM ci_leave_applications la
WHERE la.employee_id = 679
  AND la.leave_year = 2026
  AND la.status = 1
  AND la.leave_type_id = (
      SELECT leave_type_id
      FROM ci_leave_policy_mapping
      WHERE company_id = (SELECT company_id FROM ci_erp_users WHERE user_id = 679)
        AND system_leave_type = 'sick'
  );
```

### Check Pending Sick Leave Deductions

```sql
SELECT * FROM ci_sick_leave_deductions
WHERE employee_id = 679
  AND salary_month = '2026-03'
  AND is_processed = 0;
```

### Check Processed Deductions

```sql
SELECT sld.*, p.net_salary
FROM ci_sick_leave_deductions sld
JOIN ci_payslips p ON sld.payslip_id = p.payslip_id
WHERE sld.employee_id = 679
  AND sld.is_processed = 1;
```

---

## Testing Checklist

### TC5.1 - Saudi Arabia Tiered Sick Leave

#### Test Tier 1 (Days 1-30)

| Step | Action                               | Expected Result                              |
| ---- | ------------------------------------ | -------------------------------------------- |
| 1    | Employee requests 20 days sick leave | `payment_percentage = 100`, `tier_order = 1` |
| 2    | Approve leave                        | No deduction records created                 |
| 3    | Generate payslip                     | No sick leave deduction                      |

#### Test Tier 2 (Days 31-90)

| Step | Action                  | Expected Result                             |
| ---- | ----------------------- | ------------------------------------------- |
| 1    | Previous usage: 30 days | -                                           |
| 2    | Request 15 more days    | `payment_percentage = 75`, `tier_order = 2` |
| 3    | Approve leave           | Deduction records created                   |
| 4    | Check deduction         | 15 × 300 × 25% = 1,125 SAR                  |

#### Test Tier 3 (Days 91-120)

| Step | Action                  | Expected Result                            |
| ---- | ----------------------- | ------------------------------------------ |
| 1    | Previous usage: 90 days | -                                          |
| 2    | Request 10 more days    | `payment_percentage = 0`, `tier_order = 3` |
| 3    | Approve leave           | Deduction records created                  |
| 4    | Check deduction         | 10 × 300 × 100% = 3,000 SAR                |

---

## Migration Instructions

1. **Backup database** before running migration
2. Run `leave_policy_migration.sql` on both local and production
3. Verify new table: `SELECT * FROM ci_sick_leave_deductions LIMIT 1;`
4. Test with a sample sick leave request
